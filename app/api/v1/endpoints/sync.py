from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.db.database import get_db
from app.services.sync_service import SyncService
from app.schemas.sync import SyncPayload, SyncResult
import logging

router = APIRouter()
logger = logging.getLogger("estateflow.sync")


@router.post("/sync/push", response_model=SyncResult, tags=["Sync"])
def push_sync_data(payload: SyncPayload, db: Session = Depends(get_db)):
    """
    Transactional bulk synchronization from mobile device to PostgreSQL.
    """
    try:
        return SyncService.push_data(db, payload.model_dump())
    except (TypeError, ValueError) as e:
        db.rollback()
        raise HTTPException(status_code=422, detail=str(e))
    except Exception:
        db.rollback()
        logger.exception("Sync push failed")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Sync push transaction failed. Check server logs for details."
        )


@router.get("/sync/pull", tags=["Sync"])
def pull_sync_data(db: Session = Depends(get_db)):
    """
    Retrieves full remote snapshot of all properties, appointments, inquiries, and notifications.
    """
    try:
        return SyncService.pull_data(db)
    except Exception:
        logger.exception("Sync pull failed")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Sync pull failed. Check server logs for details."
        )
