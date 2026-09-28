from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.db.database import get_db
from app.services.appointment_service import AppointmentService
from app.schemas.sync import SyncRecord
import logging

router = APIRouter()
logger = logging.getLogger("estateflow.appointments")


@router.get("/appointments", tags=["Appointments"])
def list_appointments(db: Session = Depends(get_db)):
    """
    Returns all viewing appointments ordered by timestamp_epoch DESC.
    """
    return AppointmentService.get_all(db)


@router.post("/appointments", tags=["Appointments"])
def create_or_update_appointment(payload: SyncRecord, db: Session = Depends(get_db)):
    """
    Upserts an appointment by ID. Accepts both camelCase and snake_case fields.
    """
    try:
        return AppointmentService.upsert(db, payload.model_dump())
    except (TypeError, ValueError) as e:
        db.rollback()
        raise HTTPException(status_code=422, detail=str(e))
    except Exception:
        db.rollback()
        logger.exception("Appointment upsert failed")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to save appointment. Check server logs for details."
        )
