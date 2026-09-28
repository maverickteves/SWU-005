import logging
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status, Query, Path
from sqlalchemy.orm import Session
from sqlalchemy.exc import IntegrityError
from app.db.database import get_db
from app.crud import client as crud_client
from app.schemas.client import ClientCreate, ClientUpdate, ClientResponse

logger = logging.getLogger("estateflow.routers.clients")

router = APIRouter(prefix="/clients", tags=["Clients"])


@router.post(
    "",
    response_model=ClientResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new client",
    description="Registers a new prospective client/buyer. The email address must be unique."
)
def create_client(client_in: ClientCreate, db: Session = Depends(get_db)):
    existing_client = crud_client.get_client_by_email(db, client_in.email)
    if existing_client:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"A client with email '{client_in.email}' already exists."
        )
    try:
        return crud_client.create_client(db, client_in)
    except IntegrityError as e:
        db.rollback()
        logger.error(f"Database integrity error while creating client: {e}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Could not create client due to a database integrity constraint."
        )


@router.get(
    "",
    response_model=List[ClientResponse],
    status_code=status.HTTP_200_OK,
    summary="List all clients",
    description="Returns a paginated list of registered clients."
)
def list_clients(
    skip: int = Query(0, ge=0, description="Records to skip"),
    limit: int = Query(100, ge=1, le=500, description="Max records to return"),
    db: Session = Depends(get_db)
):
    return crud_client.get_clients(db, skip=skip, limit=limit)


@router.get(
    "/{client_id}",
    response_model=ClientResponse,
    status_code=status.HTTP_200_OK,
    summary="Get a client by ID",
    description="Retrieves client details by primary ID."
)
def get_client(client_id: int = Path(..., gt=0), db: Session = Depends(get_db)):
    client = crud_client.get_client(db, client_id)
    if not client:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Client with ID {client_id} was not found."
        )
    return client


@router.put(
    "/{client_id}",
    response_model=ClientResponse,
    status_code=status.HTTP_200_OK,
    summary="Update a client",
    description="Updates existing client profile information."
)
def update_client(client_id: int = Path(..., gt=0), client_in: ClientUpdate = ..., db: Session = Depends(get_db)):
    client = crud_client.get_client(db, client_id)
    if not client:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Client with ID {client_id} was not found."
        )

    if client_in.email and client_in.email != client.email:
        existing = crud_client.get_client_by_email(db, client_in.email)
        if existing and existing.id != client_id:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"A client with email '{client_in.email}' already exists."
            )

    try:
        return crud_client.update_client(db, client, client_in)
    except IntegrityError as e:
        db.rollback()
        logger.error(f"Database integrity error updating client {client_id}: {e}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Could not update client due to a database constraint violation."
        )


@router.delete(
    "/{client_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a client",
    description="Removes a client profile from the database."
)
def delete_client(client_id: int = Path(..., gt=0), db: Session = Depends(get_db)):
    client = crud_client.get_client(db, client_id)
    if not client:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Client with ID {client_id} was not found."
        )
    crud_client.delete_client(db, client)
    return None
