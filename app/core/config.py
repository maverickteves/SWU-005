import urllib.parse
from pathlib import Path
from typing import List, Union, Optional
from pydantic import AliasChoices, Field
from pydantic_settings import BaseSettings, SettingsConfigDict
from sqlalchemy.engine import URL


class Settings(BaseSettings):
    """
    Application Settings loaded from environment variables and .env file.
    Configured specifically for PostgreSQL with psycopg 3.
    """
    model_config = SettingsConfigDict(
        # Resolve the env file from the backend package, not the process CWD.
        # Android/deployment launchers commonly start uvicorn from the repo root.
        env_file=Path(__file__).resolve().parents[2] / ".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore"
    )

    # Server Settings
    APP_NAME: str = "Real Estate Management API"
    ENVIRONMENT: str = "development"
    PORT: int = 8000
    HOST: str = "0.0.0.0"

    # PostgreSQL & Supabase Database Configuration
    DATABASE_URL: Optional[str] = Field(
        default=None,
        description="Full SQLAlchemy database connection URL override (e.g. Supabase pooler or direct URI)"
    )
    DATABASE_TYPE: str = Field(
        default="postgresql",
        description="Database engine type (postgresql or sqlite for tests)"
    )
    DATABASE_HOST: str = Field(
        default="localhost",
        validation_alias=AliasChoices("DB_HOST", "PG_HOST", "DATABASE_HOST")
    )
    DATABASE_PORT: int = Field(
        default=5432,
        validation_alias=AliasChoices("DB_PORT", "PG_PORT", "DATABASE_PORT")
    )
    DATABASE_NAME: str = Field(
        default="real_estate",
        validation_alias=AliasChoices("DB_NAME", "PG_DATABASE", "DATABASE_NAME")
    )
    DATABASE_USER: str = Field(
        default="postgres",
        validation_alias=AliasChoices("DB_USER", "PG_USER", "DATABASE_USER")
    )
    DATABASE_PASSWORD: Optional[str] = Field(
        default=None,
        validation_alias=AliasChoices("DB_PASSWORD", "PG_PASSWORD", "DATABASE_PASSWORD")
    )

    # Supabase connection configuration. API keys are not used by this
    # server-side PostgreSQL integration and are intentionally not loaded.
    SUPABASE_URL: Optional[str] = Field(
        default=None,
        description="Supabase Project API URL (e.g. https://xyzcompany.supabase.co)"
    )
    SUPABASE_PROJECT_ID: Optional[str] = Field(
        default=None,
        description="Supabase Project Reference ID (e.g. hfy6wpv7xafo44fqmy6l)"
    )
    SUPABASE_DB_PASSWORD: Optional[str] = Field(
        default=None,
        description="Supabase Database Password"
    )
    SUPABASE_REGION: str = Field(
        default="aws-0-us-east-1",
        description="Supabase Pooler Region (e.g. aws-0-us-east-1, aws-0-ap-southeast-1)"
    )
    DB_SSLMODE: Optional[str] = Field(
        default=None,
        description="SSL mode for database connection (require for Supabase)"
    )

    # PostgreSQL Connection Pool Settings
    DB_POOL_SIZE: int = 10
    DB_MAX_OVERFLOW: int = 20
    DB_POOL_TIMEOUT: int = 30
    DB_POOL_RECYCLE: int = 1800

    # Security & Admin Access
    ADMIN_API_KEY: Optional[str] = None
    CORS_ORIGINS: Union[str, List[str]] = "http://localhost:3000,http://localhost:5173"

    def is_supabase(self) -> bool:
        """Determines if current configuration points to Supabase."""
        raw_url = (self.DATABASE_URL or "").lower()
        host = (self.DATABASE_HOST or "").lower()
        return (
            "supabase" in raw_url or
            "supabase" in host or
            bool(self.SUPABASE_URL) or
            bool(self.SUPABASE_PROJECT_ID)
        )

    def get_database_provider(self) -> str:
        """Returns readable name of database provider."""
        if self.is_supabase():
            return "Supabase (Cloud PostgreSQL)"
        elif self.DATABASE_TYPE.lower().strip() == "sqlite":
            return "SQLite (Local File)"
        return "PostgreSQL"

    def get_database_url(self) -> str:
        """
        Constructs the modern SQLAlchemy 2.x connection URL.
        Properly encodes credentials using sqlalchemy.engine.URL to safely handle
        special characters in passwords.
        Uses postgresql+psycopg:// for PostgreSQL with psycopg 3.
        Automatically prepares Supabase connection pooling and enforces SSL.
        """
        if self.DATABASE_URL and self.DATABASE_URL.strip():
            raw_url = self.DATABASE_URL.strip()
            # Normalize scheme to modern psycopg3 driver
            if raw_url.startswith("postgres://"):
                raw_url = raw_url.replace("postgres://", "postgresql+psycopg://", 1)
            elif raw_url.startswith("postgresql://") and not raw_url.startswith("postgresql+"):
                raw_url = raw_url.replace("postgresql://", "postgresql+psycopg://", 1)

            # Ensure Supabase has sslmode=require
            if ("supabase.co" in raw_url or "pooler.supabase.com" in raw_url) and "sslmode=" not in raw_url:
                separator = "&" if "?" in raw_url else "?"
                raw_url = f"{raw_url}{separator}sslmode=require"

            return raw_url

        # Check for Supabase direct project configuration
        if self.SUPABASE_PROJECT_ID and (self.SUPABASE_DB_PASSWORD or self.DATABASE_PASSWORD):
            pw = self.SUPABASE_DB_PASSWORD or self.DATABASE_PASSWORD
            user = f"postgres.{self.SUPABASE_PROJECT_ID}"
            host = f"{self.SUPABASE_REGION}.pooler.supabase.com"
            url_obj = URL.create(
                drivername="postgresql+psycopg",
                username=user,
                password=pw,
                host=host,
                port=6543,
                database="postgres",
                query={"sslmode": "require"}
            )
            return url_obj.render_as_string(hide_password=False)

        db_type = self.DATABASE_TYPE.lower().strip()
        if db_type == "sqlite":
            return f"sqlite:///./{self.DATABASE_NAME}.db"

        # Production / Default: PostgreSQL with psycopg3 driver
        query_params = {}
        if self.DB_SSLMODE:
            query_params["sslmode"] = self.DB_SSLMODE
        elif "supabase" in self.DATABASE_HOST.lower():
            query_params["sslmode"] = "require"

        url_obj = URL.create(
            drivername="postgresql+psycopg",
            username=self.DATABASE_USER or "postgres",
            password=self.DATABASE_PASSWORD or "",
            host=self.DATABASE_HOST,
            port=self.DATABASE_PORT,
            database=self.DATABASE_NAME,
            query=query_params if query_params else None
        )
        return url_obj.render_as_string(hide_password=False)

    def get_database_url_safe(self) -> str:
        """
        Returns the database URL with the password masked for safe logging and status endpoints.
        """
        if self.DATABASE_URL and self.DATABASE_URL.strip():
            try:
                # Mask credentials in raw URL
                parsed = urllib.parse.urlparse(self.DATABASE_URL.strip())
                netloc = parsed.netloc
                if "@" in netloc:
                    userinfo, hostinfo = netloc.split("@", 1)
                    user = userinfo.split(":", 1)[0]
                    netloc = f"{user}:***@{hostinfo}"
                return urllib.parse.urlunparse(parsed._replace(netloc=netloc))
            except Exception:
                return "postgresql+psycopg://***"

        db_type = self.DATABASE_TYPE.lower().strip()
        if db_type == "sqlite":
            return f"sqlite:///./{self.DATABASE_NAME}.db"

        url_obj = URL.create(
            drivername="postgresql+psycopg",
            username=self.DATABASE_USER or "postgres",
            password="***",
            host=self.DATABASE_HOST,
            port=self.DATABASE_PORT,
            database=self.DATABASE_NAME
        )
        # SQLAlchemy percent-encodes the masking marker; keep it readable in
        # diagnostics while never exposing the real password.
        return url_obj.render_as_string(hide_password=False).replace("%2A%2A%2A", "***")

    def get_cors_origins(self) -> List[str]:
        """Parses CORS origins into a list of strings."""
        if isinstance(self.CORS_ORIGINS, list):
            return [origin.strip() for origin in self.CORS_ORIGINS if origin.strip()]
        if self.CORS_ORIGINS == "*":
            return ["*"]
        return [origin.strip() for origin in self.CORS_ORIGINS.split(",") if origin.strip()]


settings = Settings()
