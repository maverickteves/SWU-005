import time
from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session
from sqlalchemy import desc
from app.models.inquiry import Inquiry, ChatMessage
from app.models.property import SyncProperty
from pydantic import TypeAdapter, EmailStr

email_adapter = TypeAdapter(EmailStr)


class InquiryService:

    @staticmethod
    def get_all(db: Session) -> List[Dict[str, Any]]:
        inquiries = db.query(Inquiry).order_by(desc(Inquiry.updated_at_epoch)).all()
        result = []
        for inq in inquiries:
            inq_dict = {
                "id": inq.id,
                "property_id": inq.property_id,
                "property_title": inq.property_title,
                "property_address": inq.property_address,
                "sender_name": inq.sender_name,
                "sender_email": inq.sender_email,
                "sender_phone": inq.sender_phone,
                "message": inq.message,
                "time_ago": inq.time_ago,
                "is_unread": inq.is_unread,
                "updated_at_epoch": inq.updated_at_epoch,
                "created_at": inq.created_at.isoformat() if inq.created_at else None,
                "messages": [
                    {
                        "id": m.id,
                        "sender": m.sender,
                        "text": m.message_text,
                        "time": m.time_sent,
                        "isFromMe": m.is_from_me
                    }
                    for m in inq.messages
                ]
            }
            result.append(inq_dict)
        return result

    @staticmethod
    def upsert(db: Session, data: Dict[str, Any], commit: bool = True) -> Dict[str, Any]:
        if not isinstance(data, dict) or not data.get("id"):
            raise ValueError("Each inquiry must contain a non-empty id.")
        inq_id = str(data["id"]).strip()
        if not inq_id or inq_id.lower() == "none":
            raise ValueError("Each inquiry must contain a valid id.")
        existing = db.query(Inquiry).filter(Inquiry.id == inq_id).first()

        property_id = data.get("propertyId") or data.get("property_id")
        property_title = data.get("propertyTitle") or data.get("property_title", "")
        property_address = data.get("propertyAddress") or data.get("property_address", "")
        sender_name = data.get("senderName") or data.get("sender_name", "")
        sender_email = data.get("senderEmail") or data.get("sender_email", "")
        sender_phone = data.get("senderPhone") or data.get("sender_phone", "")
        message = data.get("message", "")
        time_ago = data.get("timeAgo") or data.get("time_ago", "Just now")
        is_unread = bool(data.get("isUnread") if "isUnread" in data else data.get("is_unread", True))
        updated_at_epoch = int(data.get("updatedAt") or data.get("updated_at_epoch") or int(time.time() * 1000))

        for name, value in {
            "property_title": property_title,
            "property_address": property_address,
            "sender_name": sender_name,
            "sender_phone": sender_phone,
            "message": message,
        }.items():
            if not isinstance(value, str) or not value.strip():
                raise ValueError(f"{name} is required.")
            if len(value) > 10000:
                raise ValueError(f"{name} is too long.")
        try:
            sender_email = str(email_adapter.validate_python(sender_email))
        except Exception as exc:
            raise ValueError("sender_email must be a valid email address.") from exc
        if property_id and not db.query(SyncProperty).filter(SyncProperty.id == str(property_id)).first():
            raise ValueError(f"Property '{property_id}' does not exist.")

        if existing:
            existing.message = message
            existing.is_unread = is_unread
            existing.updated_at_epoch = updated_at_epoch
            if commit:
                db.commit()
            else:
                db.flush()
            target_inquiry = existing
        else:
            target_inquiry = Inquiry(
                id=inq_id,
                property_id=property_id,
                property_title=property_title,
                property_address=property_address,
                sender_name=sender_name,
                sender_email=sender_email,
                sender_phone=sender_phone,
                message=message,
                time_ago=time_ago,
                is_unread=is_unread,
                updated_at_epoch=updated_at_epoch
            )
            db.add(target_inquiry)
            if commit:
                db.commit()
            else:
                db.flush()

        # Insert nested messages if provided
        messages = data.get("messages", [])
        if isinstance(messages, list):
            for m in messages:
                if not isinstance(m, dict) or not m.get("id"):
                    raise ValueError("Each inquiry message must contain a non-empty id.")
                m_id = str(m["id"]).strip()
                existing_msg = db.query(ChatMessage).filter(ChatMessage.id == m_id).first()
                if not existing_msg:
                    new_msg = ChatMessage(
                        id=m_id,
                        inquiry_id=inq_id,
                        sender=m.get("sender", "Client"),
                        message_text=m.get("text") or m.get("message_text", ""),
                        time_sent=m.get("time") or m.get("time_sent", "Just now"),
                        is_from_me=bool(m.get("isFromMe") if "isFromMe" in m else m.get("is_from_me", True))
                    )
                    db.add(new_msg)
            if commit:
                db.commit()
            else:
                db.flush()

        db.refresh(target_inquiry)
        return {
            "id": target_inquiry.id,
            "property_id": target_inquiry.property_id,
            "property_title": target_inquiry.property_title,
            "property_address": target_inquiry.property_address,
            "sender_name": target_inquiry.sender_name,
            "sender_email": target_inquiry.sender_email,
            "sender_phone": target_inquiry.sender_phone,
            "message": target_inquiry.message,
            "time_ago": target_inquiry.time_ago,
            "is_unread": target_inquiry.is_unread,
            "updated_at_epoch": target_inquiry.updated_at_epoch
        }
