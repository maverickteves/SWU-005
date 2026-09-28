from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.db.database import get_db
from app.services.inquiry_service import InquiryService
from app.schemas.sync import SyncRecord
import logging

router = APIRouter()
logger = logging.getLogger("estateflow.inquiries")


@router.get("/inquiries", tags=["Inquiries"])
def list_inquiries(db: Session = Depends(get_db)):
    """
    Returns all buyer inquiries with nested chat messages, ordered by updated_at_epoch DESC.
    """
    return InquiryService.get_all(db)


@router.post("/inquiries", tags=["Inquiries"])
def create_or_update_inquiry(payload: SyncRecord, db: Session = Depends(get_db)):
    """
    Upserts an inquiry and inserts nested chat messages if present.
    """
    try:
        return InquiryService.upsert(db, payload.model_dump())
    except (TypeError, ValueError) as e:
        db.rollback()
        raise HTTPException(status_code=422, detail=str(e))
    except Exception:
        db.rollback()
        logger.exception("Inquiry upsert failed")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to save inquiry. Check server logs for details."
        )
