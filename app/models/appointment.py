from sqlalchemy import Column, String, BigInteger, Text, DateTime, ForeignKey
from sqlalchemy.sql import func
from app.db.database import Base


class ViewingAppointment(Base):
    __tablename__ = "viewing_appointments"

    id = Column(String(64), primary_key=True, index=True)
    property_id = Column(String(64), ForeignKey("sync_properties.id", ondelete="CASCADE"), nullable=True)
    property_title = Column(String(255), nullable=False)
    property_address = Column(String(255), nullable=False)
    client_name = Column(String(128), nullable=False)
    client_phone = Column(String(64), default="")
    client_email = Column(String(128), default="")
    appointment_date = Column(String(64), nullable=False)
    time_slot = Column(String(64), nullable=False)
    appointment_type = Column(String(64), default="Viewing")
    status = Column(String(32), default="UPCOMING")
    notes = Column(Text, default="")
    timestamp_epoch = Column(BigInteger, nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
