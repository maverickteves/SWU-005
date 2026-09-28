import time
from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session
from sqlalchemy import desc
from app.models.appointment import ViewingAppointment
from app.models.property import SyncProperty


class AppointmentService:

    @staticmethod
    def get_all(db: Session) -> List[ViewingAppointment]:
        return db.query(ViewingAppointment).order_by(desc(ViewingAppointment.timestamp_epoch)).all()

    @staticmethod
    def get_by_id(db: Session, appt_id: str) -> Optional[ViewingAppointment]:
        return db.query(ViewingAppointment).filter(ViewingAppointment.id == appt_id).first()

    @staticmethod
    def upsert(db: Session, data: Dict[str, Any], commit: bool = True) -> ViewingAppointment:
        if not isinstance(data, dict) or not data.get("id"):
            raise ValueError("Each appointment must contain a non-empty id.")
        appt_id = str(data["id"]).strip()
        if not appt_id or appt_id.lower() == "none":
            raise ValueError("Each appointment must contain a valid id.")
        existing = db.query(ViewingAppointment).filter(ViewingAppointment.id == appt_id).first()

        property_id = data.get("propertyId") or data.get("property_id")
        property_title = data.get("propertyTitle") or data.get("property_title", "")
        property_address = data.get("propertyAddress") or data.get("property_address", "")
        client_name = data.get("clientName") or data.get("client_name", "")
        client_phone = data.get("clientPhone") or data.get("client_phone", "")
        client_email = data.get("clientEmail") or data.get("client_email", "")
        appointment_date = data.get("date") or data.get("appointment_date", "")
        time_slot = data.get("timeSlot") or data.get("time_slot", "")
        appointment_type = data.get("type") or data.get("appointment_type", "Viewing")
        status = data.get("status", "UPCOMING")
        notes = data.get("notes", "")
        timestamp_epoch = int(data.get("timestamp") or data.get("timestamp_epoch") or int(time.time() * 1000))

        for name, value in {
            "property_title": property_title,
            "property_address": property_address,
            "client_name": client_name,
            "appointment_date": appointment_date,
            "time_slot": time_slot,
        }.items():
            if not isinstance(value, str) or not value.strip():
                raise ValueError(f"{name} is required.")
            if len(value) > 255:
                raise ValueError(f"{name} is too long.")
        if property_id and not db.query(SyncProperty).filter(SyncProperty.id == str(property_id)).first():
            raise ValueError(f"Property '{property_id}' does not exist.")

        if existing:
            existing.status = status
            existing.notes = notes
            existing.appointment_date = appointment_date
            existing.time_slot = time_slot
            if commit:
                db.commit()
            else:
                db.flush()
            db.refresh(existing)
            return existing
        else:
            new_appt = ViewingAppointment(
                id=appt_id,
                property_id=property_id,
                property_title=property_title,
                property_address=property_address,
                client_name=client_name,
                client_phone=client_phone,
                client_email=client_email,
                appointment_date=appointment_date,
                time_slot=time_slot,
                appointment_type=appointment_type,
                status=status,
                notes=notes,
                timestamp_epoch=timestamp_epoch
            )
            db.add(new_appt)
            if commit:
                db.commit()
            else:
                db.flush()
            db.refresh(new_appt)
            return new_appt
