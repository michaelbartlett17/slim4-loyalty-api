# Loyalty API <!-- omit in toc -->
## Table of Contents <!-- omit in toc -->
- [Overview](#overview)
  - [Motivation](#motivation)
  - [Goal](#goal)
  - [Task](#task)
  - [Technologies Used](#technologies-used)
  - [Evaluation Criteria](#evaluation-criteria)
- [My Learning Outcomes](#my-learning-outcomes)
  - [Learning The Slim Framework](#learning-the-slim-framework)
  - [Using PDO for Database Access](#using-pdo-for-database-access)
  - [Writing Unit Tests in PHP](#writing-unit-tests-in-php)
  - [Code Style with PHP-CS-Fixer](#code-style-with-php-cs-fixer)
- [Local Development Setup](#local-development-setup)
  - [Prerequisites](#prerequisites)
  - [Environment Variables](#environment-variables)
  - [Running the Application](#running-the-application)
  - [Database Initialization](#database-initialization)
  - [Running Tests](#running-tests)
  - [Code Style / Linting](#code-style--linting)
- [Project Structure Overview](#project-structure-overview)
- [Endpoint Documentation](#endpoint-documentation)
  - [Authentication](#authentication)
  - [Available Endpoints](#available-endpoints)
    - [`GET /users` - Gets a list of all users](#get-users---gets-a-list-of-all-users)
      - [Parameters](#parameters)
      - [Responses](#responses)
        - [Success](#success)
        - [Error](#error)
      - [Example](#example)
    - [`POST /users` - Creates a new user](#post-users---creates-a-new-user)
      - [Parameters](#parameters-1)
      - [Responses](#responses-1)
        - [Success](#success-1)
        - [Error](#error-1)
        - [Error](#error-2)
      - [Example](#example-1)
    - [`POST /users/{id}/earn` - Earn points for a user](#post-usersidearn---earn-points-for-a-user)
      - [Parameters](#parameters-2)
      - [Responses](#responses-2)
        - [Success](#success-2)
        - [Error](#error-3)
        - [Error](#error-4)
      - [Example](#example-2)
    - [`POST /users/{id}/redeem` - Redeem points for a user](#post-usersidredeem---redeem-points-for-a-user)
      - [Parameters](#parameters-3)
      - [Responses](#responses-3)
        - [Success](#success-3)
        - [Error](#error-5)
        - [Error](#error-6)
      - [Example](#example-3)
    - [`DELETE /users/{id}` - Deletes a user](#delete-usersid---deletes-a-user)
      - [Parameters](#parameters-4)
      - [Responses](#responses-4)
        - [Success](#success-4)
        - [Error](#error-7)
      - [Example](#example-4)

## Overview
A simple RESTful API written in Slim 4 for managing users and their loyalty points. This API allows you to create, delete, and list users and earn and redeem points for users.
### Motivation
This project was created as a coding challenge for a potential employer.

### Goal
Build a REST API for a simple loyalty points system. This API was to have the ability to create and delete users and allow users to earn and redeem points for their loyalty. 

### Task
- Create a PHP script that implements this REST API.
- Store the user data in a MySQL database.
- Include unit tests for at least 2 of the endpoints.
- Don't use any frameworks other than the Slim framework and any libraries for database access.

### Technologies Used
- PHP 8.4
- Slim Framework 4 for building the REST API
- MySQL for database
- Docker & Docker Compose for standardized development environment
- PHPUnit for testing
- PHP-CS-Fixer for code style and linting
- PDO for database access

### Evaluation Criteria
- Correctness - The API should work correctly and handle all error cases.
- Design - Code should follow good design principles.
- Clarity - Code should be easy to read and understand.
- Security - Code should follow best security practices.
- README.md - README.md file should clearly explain how to set up and use the API.

## My Learning Outcomes
### Learning The Slim Framework
This was my first time using the Slim framework. I've used other frameworks in PHP such as Laravel, and express.js in Node.js. I felt at home using Slim, especially since it share similar concepts and patterns with express.js.

### Using PDO for Database Access
I haven't used `PDO` for database access before. A lot of the projects I've worked on in PHP recently have used ORMs. It was neat to work with `PDO` directly and get a better understanding of how database access works in PHP at a lower level.

### Writing Unit Tests in PHP
I haven't written tests on my own in PHP before, so it was a good learning experience to get familiar with PHPUnit and refreshing my knowledge of writing tests in general.

### Code Style with PHP-CS-Fixer
Over the last couple of years, I've become a js-turned-php developer. I have a strict set of eslint rules that I like to follow in my js projects. I haven't used `php-cs-fixer` and wanted to include it in this project to help enforce code style and consistency. It was fun to learn how to use it and get it set it up.

## Local Development Setup
### Prerequisites
- Docker
- Docker Compose

### Environment Variables
Duplicate the `.env.example` file and save it as `.env`. Update the environment variables as needed.

### Running the Application
To run the application using Docker Compose, execute:
```bash
docker compose up --build
```

### Database Initialization
After getting the application up and running, you can initialize the database by running the following command:
```bash
mysql -h [host] -u [user] -p [database_name] < database/init.sql
```

### Running Tests
To run the tests, use the following command:
```bash
docker exec loyalty_php ./vendor/bin/phpunit
```

### Code Style / Linting
To check code style and linting, run:
```bash
./vendor/bin/php-cs-fixer fix --allow-risky=yes;
```

## Project Structure Overview
- `src/`
  - `Controllers/` - Contains the controller classes for handling API requests.
  - `Database` - Contains the database connection and related utility classes.
  - `Enum/` - Contains enum classes used in the application.
  - `Exceptions/` - Contains custom exception classes.
  - `Middleware/` - Contains middleware classes for the application.
  - `Models/` - Contains the model classes representing the data structures.
  - `Repositories/` - Contains repository classes for data access.
  - `Services/` - Contains service classes for business logic.
  - `routes.php` - Defines the API routes and their corresponding handlers.
- `tests/` - Contains the unit tests for the application.
- `database/` - Contains the database initialization script.
- `docker/` - Contains Docker-related files for setting up the development environment.
- `public/` - Contains the entry point for the application.

## Endpoint Documentation
### Authentication
This project uses a simple API key authentication for demonstration purposes.

**Usage:** Include the API key in the `X-API-Key` header:
```
X-API-Key: your-api-key-here
```
In a production environment, I would implment a more robust authentication mechanism, such as OAuth2 or JWT with short a expiration time and refresh tokens. 
### Available Endpoints
#### `GET /users` - Gets a list of all users
##### Parameters
- **Required Parameters:** None
- **Optional Query Parameters:** 
  - `order` (string): Order direction (valid values (case insensitive): `ASC`, `DESC`)
  - `orderBy` (string): Order by field (valid values: `id`, `name`)
  - `offset` (integer): Offset number for pagination. Defaults to 0.
  - `limit` (integer): Number of users per page. Defaults to 50. Maximum is 100.

##### Responses
###### Success
- Code: 200
- Body: Array of user objects

###### Error
- Code: 422
- Reason: Invalid parameters
- Body: a json object with the field names as keys and error messages as values
##### Example
- **Example Request:**
  ```GET /users?order=ASC&orderBy=name&offset=0&limit=1```
- **Example Response:**
  ```json
  [
    {"id": 1, "name": "John Doe", "email": "johndoe@gmail.com", "pointsBalance": 1500}
  ]
  ```
#### `POST /users` - Creates a new user
##### Parameters
- **Required Body Parameters:**
  - `name` (string): Name of the user. Max length 255 characters.
  - `email` (string - must be valid email address): Email of the user. Max length 255 characters.
##### Responses
###### Success
- Code: 201
- Body: A success message along with the created user object

###### Error
- Code: 409
- Reason: User with the given email already exists
- Body: A json object with `success` set to false and an error message

###### Error
- Code: 422
- Reason: Invalid parameters
- Body: a message regarding the invalid parameters or a json object with the field names as keys and error messages as values
##### Example
- **Example Request:**
  ```POST /users```
  ```json
  {
    "name": "Jane Smith",
    "email": "janesmith@gmail.com"
  }
  ```
- **Example Response:**
- ```json
    {
        "success": true,
        "message": "User created Successfully",
        "user": {
            "id": 2,
            "name": "Jane Smith",
            "email": "jamesmith@gmail.com",
            "pointsBalance": 0
        }
    }
    ```
#### `POST /users/{id}/earn` - Earn points for a user
`id` must be an integer representing the user's ID.
##### Parameters
- **Required Body Parameters:**
  - `points` (integer): Number of points to add to the user's balance. Must be a positive integer.
  - `description` (string): Description for earning points. Max length 255 characters.
##### Responses
###### Success
- Code: 200
- Body: A success message along with the user's new points balance

###### Error
- Code: 404
- Reason: User not found
- Body: A json object with `success` set to false and an error message

###### Error
- Code: 422
- Reason: Invalid parameters
- Body: a message regarding the invalid parameters or a json object with the field names as keys and error messages as values
##### Example
- **Example Request:**
  ```POST /users/1/earn```
  ```json
  {
    "points": 500,
    "description": "Won points playing slots."
  }
  ```
- **Example Response:**
  ```json
  {
    "success": true,
    "newBalance": 2000
  }
  ```

#### `POST /users/{id}/redeem` - Redeem points for a user
`id` must be an integer representing the user's ID.
##### Parameters
- **Required Body Parameters:**
  - `points` (integer): Number of points to subtract from the user's balance. Must be a positive integer.
  - `description` (string): Description for earning points. Max length 255 characters.

##### Responses
###### Success
- Code: 200
- Body: A success message along with the user's new points balance

###### Error
- Code: 404
- Reason: User not found
- Body: A json object with `success` set to false and an error message

###### Error
- Code: 422
- Reason: Invalid parameters
- Body: a message regarding the invalid parameters or a json object with the field names as keys and error messages as values

##### Example
- **Example Request:**
  ```POST /users/1/redeem```
  ```json
  {
    "points": 500,
    "description": "Exchanged points for a prize."
  }
  ```
- **Example Response:**
  ```json
  {
    "success": true,
    "newBalance": 1500
  }
  ```

#### `DELETE /users/{id}` - Deletes a user
`id` must be an integer representing the user's ID.
##### Parameters
- **Required Parameters:** None
##### Responses
###### Success
- Code: 204
- Body: Empty response

###### Error
- Code: 404
- Reason: User not found
- Body: A json object with `success` set to false and an error message

##### Example
- **Example Request:**
  ```DELETE /users/1```
- **Example Response:**
  Empty response with HTTP status code 204