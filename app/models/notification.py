from sqlalchemy import Column, String, Boolean, Text, DateTime
from sqlalchemy.sql import func
from app.db.database import Base


class Notification(Base):
    __tablename__ = "notifications"

    id = Column(String(64), primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    message = Column(Text, nullable=False)
    timestamp_formatted = Column(String(64), nullable=False)
    notification_type = Column(String(64), nullable=False)
    is_read = Column(Boolean, default=False)
    target_id = Column(String(64), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
