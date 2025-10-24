# bus-ticket-system

**bus-ticket-system** is a project built with **PHP** and **SQLite**. It allows users to book bus tickets, companies to manage trips, and admins to oversee the platform. The project includes a **Docker setup** for easy deployment and comes with a **pre-seeded database** for testing.

---

## Features

-   Role-based access: Admin, Company, User
-   Create, view, and manage trips
-   Book tickets and manage bookings
-   SQLite database with seeder for test users
-   Lightweight PHP backend
-   Dockerized environment for easy setup

---

## Requirements

-   Docker
-   Web browser
-   (Optional) Docker Compose for advanced setups

---

## Installation

### Using Docker

1.  **Clone the repository**
    ```bash
    git clone https://github.com/serdemd/bus-ticket-system.git
    cd bus-ticket-system
    ```

2.  **Build with Docker**
    ```bash
    docker compose build
    ```

3.  **Start with Docker**
    ```bash
    docker compose up
    ```

4.  **Create Database**
    To create and seed the database, navigate to:
    `localhost:8080/createdb`

    *Note: This process might take a few minutes to complete. The seeder automatically generates test users, coupons, and trips for **today** and **tomorrow**. If you are testing late in the day and do not see any trips listed for "today", it is likely because their scheduled departure times have already passed.*

---

## Test Users

The database is pre-seeded with the following test users.

**Password:** The password for all test users is `Aa12345!`

| Name | Email | Role | Company ID | Gender |
| :--- | :--- | :--- | :--- | :--- |
| Admin User | `admin@example.com` | admin | null | null |
| Company Admin | `companyadmin@example.com` | company | $company_id | null |
| John | `john@example.com` | user | null | male |
| Jane | `jane@example.com` | user | null | female |

## Test Coupons

The seeder also creates the following test coupon codes:

```php
// [code, discount_percentage, company_id, quantity, expiry_date]

// Company specific
['YAVUZ50', 50, $company_id, 8, $in_30_days],

// All companies
['ALL40', 40, null, 10, $in_30_days],

// Used coupon (quantity 1, will be used by seeder)
['USED20', 20, null, 1, $in_30_days],

// Expired coupon
['PAST30', 30, null, 9, $week_ago],