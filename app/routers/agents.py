import logging
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status, Query, Path
from sqlalchemy.orm import Session
from sqlalchemy.exc import IntegrityError
from app.db.database import get_db
from app.crud import agent as crud_agent
from app.schemas.agent import AgentCreate, AgentUpdate, AgentResponse

logger = logging.getLogger("estateflow.routers.agents")

router = APIRouter(prefix="/agents", tags=["Agents"])


@router.post(
    "",
    response_model=AgentResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new agent",
    description="Registers a new real estate agent. The email address must be unique."
)
def create_agent(agent_in: AgentCreate, db: Session = Depends(get_db)):
    # Check for duplicate email before inserting
    existing_agent = crud_agent.get_agent_by_email(db, agent_in.email)
    if existing_agent:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"An agent with email '{agent_in.email}' already exists."
        )
    try:
        return crud_agent.create_agent(db, agent_in)
    except IntegrityError as e:
        db.rollback()
        logger.error(f"Database integrity error while creating agent: {e}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Could not create agent due to a database integrity constraint."
        )


@router.get(
    "",
    response_model=List[AgentResponse],
    status_code=status.HTTP_200_OK,
    summary="List all agents",
    description="Returns a paginated list of all real estate agents."
)
def list_agents(
    skip: int = Query(0, ge=0, description="Records to skip"),
    limit: int = Query(100, ge=1, le=500, description="Max records to return"),
    db: Session = Depends(get_db)
):
    return crud_agent.get_agents(db, skip=skip, limit=limit)


@router.get(
    "/{agent_id}",
    response_model=AgentResponse,
    status_code=status.HTTP_200_OK,
    summary="Get an agent by ID",
    description="Retrieves a specific real estate agent by their primary ID."
)
def get_agent(agent_id: int = Path(..., gt=0), db: Session = Depends(get_db)):
    agent = crud_agent.get_agent(db, agent_id)
    if not agent:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Agent with ID {agent_id} was not found."
        )
    return agent


@router.put(
    "/{agent_id}",
    response_model=AgentResponse,
    status_code=status.HTTP_200_OK,
    summary="Update an agent",
    description="Updates information for an existing agent."
)
def update_agent(agent_id: int = Path(..., gt=0), agent_in: AgentUpdate = ..., db: Session = Depends(get_db)):
    agent = crud_agent.get_agent(db, agent_id)
    if not agent:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Agent with ID {agent_id} was not found."
        )

    # If updating email, check uniqueness
    if agent_in.email and agent_in.email != agent.email:
        existing = crud_agent.get_agent_by_email(db, agent_in.email)
        if existing and existing.id != agent_id:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"An agent with email '{agent_in.email}' already exists."
            )

    try:
        return crud_agent.update_agent(db, agent, agent_in)
    except IntegrityError as e:
        db.rollback()
        logger.error(f"Database integrity error updating agent {agent_id}: {e}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Could not update agent due to a database constraint violation."
        )


@router.delete(
    "/{agent_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete an agent",
    description="Deletes an agent record and any associated listings."
)
def delete_agent(agent_id: int = Path(..., gt=0), db: Session = Depends(get_db)):
    agent = crud_agent.get_agent(db, agent_id)
    if not agent:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Agent with ID {agent_id} was not found."
        )
    crud_agent.delete_agent(db, agent)
    return None
