# ETS Language Booking Platform

A full-stack web application for managing language assessment sessions and reservations. The platform allows users to browse available sessions, book their places, manage their reservations, and update their account information. Administrators can manage language sessions and their capacity.

## Features

### Authentication and user management
- User registration and login.
- JWT-based authentication using secure HTTP-only cookies.
- Protected pages and API endpoints.
- Role-based access control (`ROLE_USER`, `ROLE_ADMIN`).
- Account profile management.

### Language sessions
- Browse available language assessment sessions.
- Paginated session listing.
- View session details, including language, date, location, and capacity.
- Administrative session management.
- Track available places and reservation counts.

### Reservations
- Book a place in a session.
- Cancel an existing reservation.
- Prevent duplicate active reservations for the same user and session.
- Reactivate a previously cancelled reservation.
- Enforce session capacity limits.
- Protect booking and cancellation operations against concurrent requests using MongoDB transactions and atomic updates.

### Testing
- Backend automated tests with PHPUnit.
- Frontend component tests with Jest and React Testing Library.
- Reservation concurrency testing.

## Architecture

The application is composed of three main services:

| Component | Technology | Description |
|---|---|---|
| Frontend | Next.js, React, TypeScript | User interface |
| Backend | Symfony, PHP 8.4+ | REST API and business logic |
| Database | MongoDB 7 | Data persistence |

### Architecture overview

```text
                 Web Browser
                      |
                      | HTTP
                      v
              Next.js Frontend
                 Port 3000
                      |
                      | REST API
                      | JWT Cookie
                      v
               Symfony Backend
                 Port 8000
                      |
                      | MongoDB ODM
                      v
                 MongoDB 7
                 Port 27017
              Replica Set (rs0)
```

### Backend

The backend is built with Symfony and provides REST API endpoints.

Main responsibilities:
- Authentication and authorization.
- User and account management.
- Session management.
- Reservation business logic.
- Input validation and API error handling.
- MongoDB persistence through Doctrine MongoDB ODM.
- Transaction management for reservation consistency.

### Frontend

The frontend is built with Next.js, React, and TypeScript.

Main responsibilities:
- Authentication interfaces.
- Protected application pages.
- Session browsing and pagination.
- Reservation management.
- Account profile management.
- Communication with the backend REST API.

### Database

MongoDB 7 is used as the main database.

The database runs as a single-node replica set (`rs0`) to support transactions.

Reservation consistency is enforced through:
- Unique user/session reservation constraints.
- Atomic capacity updates.
- MongoDB transactions.

## Prerequisites

You need the following tools installed:

- Docker
- Docker Compose v2 or later
- Git

You do not need to install PHP, Composer, Node.js, or MongoDB locally when using the Docker Compose setup.

## Project structure

```text
ets-language-booking/
│
├── backend/
│   ├── config/
│   ├── public/
│   ├── src/
│   │   ├── Controller/
│   │   ├── Document/
│   │   ├── Repository/
│   │   ├── Service/
│   │   └── ...
│   ├── tests/
│   │   ├── Unit/
│   │   └── Integration/
│   ├── composer.json
│   └── Dockerfile
│
├── frontend/
│   ├── src/
│   │   ├── app/
│   │   ├── components/
│   │   ├── features/
│   │   ├── lib/
│   │   └── types/
│   ├── tests/
│   ├── jest.config.js
│   ├── jest.setup.ts
│   ├── package.json
│   └── Dockerfile
│
├── docker-compose.yml
└── README.md
```

## Getting started with Docker Compose

### 1. Clone the repository

```bash
git clone <REPOSITORY_URL>
cd ets-language-booking
```

### 2. Configure environment variables

Review the environment variables used by:
- `docker-compose.yml`
- `backend/.env` and any environment-specific configuration
- Frontend configuration

Make sure the backend MongoDB connection points to the Compose service hostname (`mongodb`), not `localhost`.

The frontend API URL must point to the backend API. For local browser access, the default API base URL is:

```text
http://localhost:8000/api
```

Configure the JWT keys and secrets required by the backend before starting the application.

Do not commit production secrets or private JWT keys to Git.

### 3. Build and start the application

From the project root:

```bash
docker compose up --build -d
```

This command builds the application images and starts the three services in the background.

### 4. Check service status

```bash
docker compose ps
```

Check the logs:

```bash
docker compose logs -f
```

Or inspect an individual service:

```bash
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f mongodb
```

### 5. Access the application

Once the services are running:

| Service | URL |
|---|---|
| Frontend | http://localhost:3000 |
| Backend API | http://localhost:8000/api |
| MongoDB | localhost:27017 |

Open the frontend in your browser:

```text
http://localhost:3000
```

## Useful Docker Compose commands

### Start services

```bash
docker compose up -d
```

### Rebuild and restart services

```bash
docker compose up --build -d
```

### Stop services

```bash
docker compose stop
```

### Stop and remove containers

```bash
docker compose down
```

### Restart a specific service

```bash
docker compose restart backend
```

### Access the backend container

```bash
docker compose exec backend sh
```

### Access the frontend container

```bash
docker compose exec frontend sh
```

### Access MongoDB

```bash
docker compose exec mongodb mongosh
```

**Note:** `docker compose down -v` also removes named volumes and can permanently delete the persisted MongoDB data. Use it only when you intentionally want to reset the database.

## Running tests

### Backend tests

Run all PHPUnit tests inside the backend container:

```bash
docker compose exec backend php bin/phpunit
```

Run a specific test file:

```bash
docker compose exec backend php bin/phpunit tests/Integration/ReservationConcurrencyTest.php
```

### Frontend tests

Run the complete Jest test suite:

```bash
docker compose exec frontend npm test -- --runInBand
```

Or, from the frontend directory on a machine with Node.js installed:

```bash
cd frontend
npm install
npm test
```

The frontend test suite covers:
- Account profile updates.
- Session listing and pagination.
- Reservation submission.
- Authentication forms.
- Protected pages.

## Development workflow

The recommended development workflow is:

1. Start the Docker Compose services.
2. Implement changes in the backend or frontend.
3. Run the relevant automated tests.
4. Inspect application logs if an error occurs.
5. Verify the complete application through the browser.
6. Commit the changes to Git.

Useful commands:

```bash
# Inspect running containers
docker compose ps

# Follow backend logs
docker compose logs -f backend

# Follow frontend logs
docker compose logs -f frontend

# Run backend tests
docker compose exec backend php bin/phpunit

# Run frontend tests
docker compose exec frontend npm test -- --runInBand
```

## Security and reliability considerations

The application incorporates several mechanisms to improve security and data consistency:

- JWT authentication with HTTP-only cookies.
- Role-based authorization.
- Protected backend endpoints.
- CORS configuration for frontend/backend communication.
- Server-side validation.
- Unique reservation constraints.
- Atomic database updates.
- MongoDB transactions for concurrent reservation operations.

## License

This project is proprietary. All rights reserved.