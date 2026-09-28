from datetime import datetime, timezone
from typing import Dict, Any, List
from sqlalchemy.orm import Session
from sqlalchemy import text
from app.services.property_service import PropertyService
from app.services.appointment_service import AppointmentService
from app.services.inquiry_service import InquiryService
from app.models.notification import Notification


class SyncService:

    @staticmethod
    def push_data(db: Session, payload: Dict[str, Any]) -> Dict[str, Any]:
        """
        Executes an atomic transactional push from mobile app to PostgreSQL.
        """
        try:
            properties = payload.get("properties", [])
            appointments = payload.get("appointments", [])
            inquiries = payload.get("inquiries", [])
            notifications = payload.get("notifications", [])

            if isinstance(properties, list):
                for p in properties:
                    PropertyService.upsert(db, p, commit=False)

            if isinstance(appointments, list):
                for a in appointments:
                    AppointmentService.upsert(db, a, commit=False)

            if isinstance(inquiries, list):
                for inq in inquiries:
                    InquiryService.upsert(db, inq, commit=False)

            if isinstance(notifications, list):
                for n in notifications:
                    if not isinstance(n, dict) or not n.get("id"):
                        raise ValueError("Each notification must contain a non-empty id.")
                    n_id = str(n["id"]).strip()
                    if not n_id or n_id.lower() == "none":
                        raise ValueError("Each notification must contain a valid id.")
                    title = str(n.get("title", "")).strip()
                    message = str(n.get("message", "")).strip()
                    if not title or not message:
                        raise ValueError("Notification title and message are required.")
                    existing = db.query(Notification).filter(Notification.id == n_id).first()
                    if not existing:
                        new_n = Notification(
                            id=n_id,
                            title=title[:255],
                            message=message,
                            timestamp_formatted=n.get("timestampFormatted") or n.get("timestamp_formatted", ""),
                            notification_type=n.get("notificationType") or n.get("notification_type", "GENERAL"),
                            is_read=bool(n.get("isRead") if "isRead" in n else n.get("is_read", False)),
                            target_id=n.get("targetId") or n.get("target_id")
                        )
                        db.add(new_n)
                db.flush()

            # Commit exactly once so a malformed later record rolls back the
            # complete synchronization batch.
            db.commit()

            return {
                "success": True,
                "message": "Successfully pushed and synchronized local data to PostgreSQL!",
                "syncedAt": datetime.now(timezone.utc).isoformat()
            }
        except Exception as e:
            db.rollback()
            raise e

    @staticmethod
    def pull_data(db: Session) -> Dict[str, Any]:
        """
        Retrieves snapshot of all properties, appointments, inquiries, and notifications.
        """
        props = db.execute(text("SELECT * FROM sync_properties")).mappings().all()
        appts = db.execute(text("SELECT * FROM viewing_appointments")).mappings().all()
        inqs = db.execute(text("SELECT * FROM inquiries")).mappings().all()
        notifs = db.execute(text("SELECT * FROM notifications")).mappings().all()

        return {
            "properties": [dict(row) for row in props],
            "appointments": [dict(row) for row in appts],
            "inquiries": [dict(row) for row in inqs],
            "notifications": [dict(row) for row in notifs]
        }
