import logging
from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query, Path
from sqlalchemy.orm import Session
from sqlalchemy.exc import IntegrityError
from app.db.database import get_db
from app.crud import property as crud_property
from app.crud import agent as crud_agent
from app.schemas.property import PropertyCreate, PropertyUpdate, PropertyResponse

logger = logging.getLogger("estateflow.routers.properties")

router = APIRouter(prefix="/properties", tags=["Properties"])


@router.post(
    "",
    response_model=PropertyResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new property listing",
    description="Adds a new real estate listing associated with a valid agent."
)
def create_property(property_in: PropertyCreate, db: Session = Depends(get_db)):
    # Verify the referenced agent exists (Requirement 8)
    agent = crud_agent.get_agent(db, property_in.agent_id)
    if not agent:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Agent with ID {property_in.agent_id} does not exist."
        )

    try:
        return crud_property.create_property(db, property_in)
    except IntegrityError as e:
        db.rollback()
        logger.error(f"Database integrity error creating property: {e}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Could not create property due to a database constraint violation."
        )


@router.get(
    "",
    response_model=List[PropertyResponse],
    status_code=status.HTTP_200_OK,
    summary="List all properties",
    description="Retrieves a list of properties with optional filters by city, property type, status, and agent."
)
def list_properties(
    skip: int = Query(0, ge=0, description="Records to skip"),
    limit: int = Query(100, ge=1, le=500, description="Max records to return"),
    city: Optional[str] = Query(None, min_length=1, max_length=100, description="Filter by city name"),
    property_type: Optional[str] = Query(None, min_length=1, max_length=100, description="Filter by property type (e.g. House, Condo)"),
    status: Optional[str] = Query(None, min_length=1, max_length=50, description="Filter by status (e.g. Available, Sold)"),
    agent_id: Optional[int] = Query(None, gt=0, description="Filter by agent ID"),
    db: Session = Depends(get_db)
):
    return crud_property.get_properties(
        db,
        skip=skip,
        limit=limit,
        city=city,
        property_type=property_type,
        status=status,
        agent_id=agent_id
    )


@router.get(
    "/{property_id}",
    response_model=PropertyResponse,
    status_code=status.HTTP_200_OK,
    summary="Get a property by ID",
    description="Retrieves detailed property information including assigned agent details."
)
def get_property(property_id: int = Path(..., gt=0), db: Session = Depends(get_db)):
    prop = crud_property.get_property(db, property_id)
    if not prop:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Property with ID {property_id} was not found."
        )
    return prop


@router.put(
    "/{property_id}",
    response_model=PropertyResponse,
    status_code=status.HTTP_200_OK,
    summary="Update a property",
    description="Updates listing details for an existing property."
)
def update_property(property_id: int = Path(..., gt=0), property_in: PropertyUpdate = ..., db: Session = Depends(get_db)):
    prop = crud_property.get_property(db, property_id)
    if not prop:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Property with ID {property_id} was not found."
        )

    # If updating agent_id, verify the target agent exists
    if property_in.agent_id is not None:
        agent = crud_agent.get_agent(db, property_in.agent_id)
        if not agent:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Agent with ID {property_in.agent_id} does not exist."
            )

    try:
        return crud_property.update_property(db, prop, property_in)
    except IntegrityError as e:
        db.rollback()
        logger.error(f"Database integrity error updating property {property_id}: {e}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Could not update property due to a database constraint violation."
        )


@router.delete(
    "/{property_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a property",
    description="Deletes a property listing by ID."
)
def delete_property(property_id: int = Path(..., gt=0), db: Session = Depends(get_db)):
    prop = crud_property.get_property(db, property_id)
    if not prop:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Property with ID {property_id} was not found."
        )
    crud_property.delete_property(db, prop)
    return None
