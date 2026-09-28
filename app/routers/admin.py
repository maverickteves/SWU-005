import logging
import hmac
from typing import Dict, Any
from fastapi import APIRouter, Depends, HTTPException, Header, status
from sqlalchemy.orm import Session
from sqlalchemy import text
from app.core.config import settings
from app.db.database import get_db, test_db_connection, engine

logger = logging.getLogger("estateflow.routers.admin")

router = APIRouter(prefix="/admin", tags=["Admin & System Configuration"])


def verify_admin_access(x_admin_key: str = Header(None, alias="X-Admin-Key")):
    """
    Guards administrative endpoints.
    Normal clients/buyers do not possess the admin key.
    """
    configured_key = settings.ADMIN_API_KEY
    if not configured_key or not x_admin_key or not hmac.compare_digest(x_admin_key, configured_key):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied. Administrative privileges are required."
        )
    return True


@router.get(
    "/db-status",
    summary="Get Database Architecture & Pool Status (Admin Only)",
    description="Returns sanitized connection and pool diagnostics for administrators."
)
def get_admin_db_status(
    authorized: bool = Depends(verify_admin_access),
    db: Session = Depends(get_db)
) -> Dict[str, Any]:
    test_result = test_db_connection()

    pool_info = {
        "pool_size": settings.DB_POOL_SIZE,
        "max_overflow": settings.DB_MAX_OVERFLOW,
        "pool_timeout": settings.DB_POOL_TIMEOUT,
        "pool_recycle_seconds": settings.DB_POOL_RECYCLE,
        "checkedin": engine.pool.checkedin() if hasattr(engine.pool, "checkedin") else None,
        "checkedout": engine.pool.checkedout() if hasattr(engine.pool, "checkedout") else None,
    }

    return {
        "database_type": settings.DATABASE_TYPE,
        "database_name": settings.DATABASE_NAME,
        "database_host": settings.DATABASE_HOST,
        "database_port": settings.DATABASE_PORT,
        "database_user": settings.DATABASE_USER,
        "connection_url_safe": settings.get_database_url_safe(),
        "is_connected": test_result.get("connected", False),
        "latency_ms": test_result.get("latency_ms"),
        "postgres_version": test_result.get("postgres_version"),
        "pool_status": pool_info,
        "security_note": "Database passwords are never returned over API responses."
    }


@router.post(
    "/db-ping",
    summary="Ping Database Engine (Admin Only)",
    description="Executes a test query to verify live PostgreSQL connection health."
)
def ping_database(
    authorized: bool = Depends(verify_admin_access),
    db: Session = Depends(get_db)
):
    try:
        res = db.execute(text("SELECT 1 AS ok")).scalar()
        return {"status": "SUCCESS", "ping": res, "message": "Database response verified."}
    except Exception as e:
        logger.error(f"Admin ping failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Database ping failed."
        )
