from typing import List, Optional
from sqlalchemy.orm import Session
from sqlalchemy import desc
from app.db.models import Property
from app.schemas.property import PropertyCreate, PropertyUpdate


def get_property(db: Session, property_id: int) -> Optional[Property]:
    """Retrieve a single property by primary key ID."""
    return db.query(Property).filter(Property.id == property_id).first()


def get_properties(
    db: Session,
    skip: int = 0,
    limit: int = 100,
    city: Optional[str] = None,
    property_type: Optional[str] = None,
    status: Optional[str] = None,
    agent_id: Optional[int] = None
) -> List[Property]:
    """Retrieve a list of properties with optional filtering and pagination."""
    query = db.query(Property)
    if city:
        query = query.filter(Property.city.ilike(f"%{city}%"))
    if property_type:
        query = query.filter(Property.property_type.ilike(property_type))
    if status:
        query = query.filter(Property.status.ilike(status))
    if agent_id:
        query = query.filter(Property.agent_id == agent_id)
    return query.order_by(desc(Property.created_at)).offset(skip).limit(limit).all()


def create_property(db: Session, property_in: PropertyCreate) -> Property:
    """Create and persist a new property listing."""
    db_property = Property(
        title=property_in.title,
        description=property_in.description,
        property_type=property_in.property_type,
        price=property_in.price,
        address=property_in.address,
        city=property_in.city,
        bedrooms=property_in.bedrooms,
        bathrooms=property_in.bathrooms,
        area=property_in.area,
        status=property_in.status,
        agent_id=property_in.agent_id,
    )
    db.add(db_property)
    db.commit()
    db.refresh(db_property)
    return db_property


def update_property(db: Session, db_property: Property, property_in: PropertyUpdate) -> Property:
    """Update fields on an existing property."""
    update_data = property_in.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        setattr(db_property, field, value)
    db.commit()
    db.refresh(db_property)
    return db_property


def delete_property(db: Session, db_property: Property) -> None:
    """Delete a property listing from the database."""
    db.delete(db_property)
    db.commit()
