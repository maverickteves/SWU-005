from typing import List, Optional
from sqlalchemy.orm import Session
from app.db.models import Client
from app.schemas.client import ClientCreate, ClientUpdate


def get_client(db: Session, client_id: int) -> Optional[Client]:
    """Retrieve a single client by primary key ID."""
    return db.query(Client).filter(Client.id == client_id).first()


def get_client_by_email(db: Session, email: str) -> Optional[Client]:
    """Retrieve a client by email address."""
    return db.query(Client).filter(Client.email == email).first()


def get_clients(db: Session, skip: int = 0, limit: int = 100) -> List[Client]:
    """Retrieve a list of clients with pagination."""
    return db.query(Client).offset(skip).limit(limit).all()


def create_client(db: Session, client_in: ClientCreate) -> Client:
    """Create and persist a new client record."""
    db_client = Client(
        first_name=client_in.first_name,
        last_name=client_in.last_name,
        email=client_in.email,
        phone=client_in.phone
    )
    db.add(db_client)
    db.commit()
    db.refresh(db_client)
    return db_client


def update_client(db: Session, db_client: Client, client_in: ClientUpdate) -> Client:
    """Update fields on an existing client."""
    update_data = client_in.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        setattr(db_client, field, value)
    db.commit()
    db.refresh(db_client)
    return db_client


def delete_client(db: Session, db_client: Client) -> None:
    """Delete a client record from the database."""
    db.delete(db_client)
    db.commit()
