import logging
from typing import Generator, Dict, Any
from sqlalchemy import create_engine, text
from sqlalchemy.orm import declarative_base, sessionmaker, Session
from app.core.config import settings

logger = logging.getLogger("estateflow.database")

database_url = settings.get_database_url()

# Engine creation with support for SQLite, PostgreSQL, and Supabase
connect_args = {}
engine_kwargs = {"echo": False}

if database_url.startswith("sqlite"):
    connect_args["check_same_thread"] = False
    engine_kwargs["connect_args"] = connect_args
else:
    # Supabase's transaction pooler may route successive transactions to
    # different PostgreSQL sessions. Disable psycopg prepared statements so
    # queries don't refer to statements missing from the current backend.
    if ":6543" in database_url or "pooler.supabase.com" in database_url:
        connect_args["prepare_threshold"] = None
        logger.info("Supabase transaction pooler detected: prepared statements disabled")

    if connect_args:
        engine_kwargs["connect_args"] = connect_args

    # Production connection pool options for PostgreSQL & Supabase
    engine_kwargs.update({
        "pool_pre_ping": True,           # Disconnect detection & self-healing
        "pool_size": settings.DB_POOL_SIZE,
        "max_overflow": settings.DB_MAX_OVERFLOW,
        "pool_timeout": settings.DB_POOL_TIMEOUT,
        "pool_recycle": settings.DB_POOL_RECYCLE,
    })

try:
    engine = create_engine(database_url, **engine_kwargs)
    logger.info(f"Database engine initialized for {settings.get_database_url_safe()}")
except Exception as e:
    logger.error(f"Failed to create database engine for {settings.get_database_url_safe()}: {e}")
    # Fallback to standard engine with pool_pre_ping enabled
    engine = create_engine(database_url, pool_pre_ping=True)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()


def get_db() -> Generator[Session, None, None]:
    """
    FastAPI dependency that yields a database session per request.
    Guarantees session rollback on error and proper closure.
    """
    db = SessionLocal()
    try:
        yield db
    except Exception:
        db.rollback()
        raise
    finally:
        db.close()


def test_db_connection() -> Dict[str, Any]:
    """
    Validates PostgreSQL connectivity with a lightweight ping.
    Returns sanitized status and latency without leaking credentials.
    """
    import time
    start_time = time.time()
    try:
        with engine.connect() as connection:
            if connection.dialect.name == "sqlite":
                result = connection.execute(text("SELECT 1 AS ping, CURRENT_TIMESTAMP AS now")).mappings().first()
                version = "SQLite"
            else:
                result = connection.execute(text("SELECT 1 AS ping, NOW() AS now, version() AS version")).mappings().first()
                version = str(result["version"]).split(" on ")[0] if result else "PostgreSQL"
            latency_ms = round((time.time() - start_time) * 1000, 2)
            return {
                "connected": True,
                "latency_ms": latency_ms,
                "server_time": str(result["now"]) if result else None,
                "postgres_version": version,
                "database": settings.DATABASE_NAME,
                "host": settings.DATABASE_HOST,
                "provider": settings.get_database_provider()
            }
    except Exception as e:
        logger.warning(f"Database health check failed: {e}")
        return {
            "connected": False,
            "latency_ms": round((time.time() - start_time) * 1000, 2),
            "error_message": "Database server unreachable. Verify PostgreSQL/Supabase service is running and credentials are valid.",
            "database": settings.DATABASE_NAME,
            "provider": settings.get_database_provider()
        }
