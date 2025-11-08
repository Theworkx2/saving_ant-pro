# Saving Ant - Personal Finance Management System

A web-based personal finance management system built with PHP and MySQL that helps users track their savings and manage transactions.

## Features

- User authentication and authorization
- Transaction management (deposits and withdrawals)
- Multiple payment method support (MTN Mobile Money, Airtel Money, Bank Transfer)
- Real-time balance tracking
- Transaction history with pagination
- Bulk transaction management
- Admin dashboard for user management
- Responsive design for mobile and desktop

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (for Apache)
- PHP PDO extension
- PHP session extension

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/saving_ant.git
```

2. Import the database schema:
- Navigate to the `setup` folder
- Import `database.sql` into your MySQL server

3. Configure the database connection:
- Copy `inc/db.example.php` to `inc/db.php`
- Update the database credentials in `inc/db.php`

4. Set up your web server:
- Point your web server to the project directory
- Ensure the document root is set correctly

5. Access the application:
- Open your browser and navigate to the project URL
- Default admin credentials:
  - Username: admin
  - Password: admin123

## Security Features

- CSRF protection
- Password hashing
- Prepared statements for SQL queries
- Input sanitization
- Session management
- Role-based access control

## License

This project is licensed under the MIT License - see the LICENSE file for details.