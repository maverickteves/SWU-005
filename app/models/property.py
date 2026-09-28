"""Models used by the mobile synchronization API.

The CRUD API's ``properties`` table is deliberately kept separate from the
mobile app's richer listing shape.  Earlier versions mapped both shapes to
the same table, which caused SQL errors as soon as the sync endpoints were
used against the CRUD schema.
"""
from sqlalchemy import Boolean, Column, DateTime, Integer, Numeric, String, Text
from sqlalchemy.sql import func
from app.db.database import Base


class SyncProperty(Base):
    __tablename__ = "sync_properties"

    id = Column(String(64), primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    address = Column(String(255), nullable=False)
    city_state_zip = Column(String(128), nullable=False, default="")
    price = Column(Integer, nullable=False, default=0)
    price_formatted = Column(String(64), nullable=False, default="")
    is_rental = Column(Boolean, default=False)
    beds = Column(Numeric(4, 1), nullable=False, default=3)
    baths = Column(Numeric(4, 1), nullable=False, default=2)
    sqft = Column(Integer, nullable=False, default=1500)
    property_type = Column(String(64), nullable=False, default="House")
    description = Column(Text, nullable=False, default="")
    image_res_id = Column(Integer, default=0)
    media_uris = Column(Text, default="")
    is_favorite = Column(Boolean, default=False)
    status = Column(String(64), default="Active")
    available_dates = Column(Text, default="")
    available_time_slots = Column(Text, default="")
    amenities = Column(Text, default="")
    year_built = Column(Integer, default=2023)
    agent_name = Column(String(128), default="Sarah Jenkins")
    agent_title = Column(String(255), default="")
    agent_phone = Column(String(64), default="")
    agent_email = Column(String(128), default="")
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)


__all__ = ["SyncProperty"]
