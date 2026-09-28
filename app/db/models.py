from sqlalchemy import CheckConstraint, Column, Integer, BigInteger, String, Numeric, Text, DateTime, ForeignKey, Index
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.db.database import Base


class Agent(Base):
    __tablename__ = "agents"

    id = Column(
        BigInteger().with_variant(Integer, "sqlite"),
        primary_key=True,
        index=True,
        autoincrement=True
    )
    first_name = Column(String(100), nullable=False)
    last_name = Column(String(100), nullable=False)
    email = Column(String(255), unique=True, index=True, nullable=False)
    phone = Column(String(50), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    properties = relationship("Property", back_populates="agent", cascade="all, delete-orphan")


class Client(Base):
    __tablename__ = "clients"

    id = Column(
        BigInteger().with_variant(Integer, "sqlite"),
        primary_key=True,
        index=True,
        autoincrement=True
    )
    first_name = Column(String(100), nullable=False)
    last_name = Column(String(100), nullable=False)
    email = Column(String(255), unique=True, index=True, nullable=False)
    phone = Column(String(50), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class Property(Base):
    __tablename__ = "properties"
    __table_args__ = (
        CheckConstraint("price > 0", name="ck_properties_price_positive"),
        CheckConstraint("bedrooms >= 0", name="ck_properties_bedrooms_nonnegative"),
        CheckConstraint("bathrooms >= 0", name="ck_properties_bathrooms_nonnegative"),
        CheckConstraint("area > 0", name="ck_properties_area_positive"),
        CheckConstraint(
            "status IN ('available', 'pending', 'sold', 'rented', 'under_offer')",
            name="ck_properties_status_valid",
        ),
        Index("ix_properties_city_status", "city", "status"),
    )

    id = Column(
        BigInteger().with_variant(Integer, "sqlite"),
        primary_key=True,
        index=True,
        autoincrement=True
    )
    title = Column(String(255), nullable=False, index=True)
    description = Column(Text, nullable=True)
    property_type = Column(String(100), nullable=False, index=True)
    price = Column(Numeric(12, 2), nullable=False)
    address = Column(String(255), nullable=False)
    city = Column(String(100), nullable=False, index=True)
    bedrooms = Column(Integer, nullable=False, default=1)
    bathrooms = Column(Numeric(3, 1), nullable=False, default=1.0)
    area = Column(Numeric(10, 2), nullable=False, default=1.0)
    status = Column(String(50), nullable=False, default="available", index=True)
    agent_id = Column(
        BigInteger().with_variant(Integer, "sqlite"),
        ForeignKey("agents.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    agent = relationship("Agent", back_populates="properties")
