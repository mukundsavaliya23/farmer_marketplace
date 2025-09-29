# Farmer Marketplace

A web-based platform connecting farmers directly with consumers and retailers for agricultural products.

## Features

- User authentication (Farmers, Buyers, Admin)
- Product listing and management
- Price prediction for agricultural products
- Chatbot for customer support
- Admin dashboard for managing users and products
- Farmer dashboard for managing inventory and orders

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/farmer-marketplace.git
   ```

2. Import the database:
   - Create a new MySQL database
   - Import the SQL file from `database/farmer_marketplace.sql`

3. Configure the application:
   - Copy `.env.example` to `.env`
   - Update the database credentials in `.env`

4. Start your local server and access the application in your browser.

## Directory Structure

```
farmer-marketplace/
├── api/                 # API endpoints
├── assets/              # Static assets (CSS, JS, images)
├── chatbot/             # Chatbot implementation
├── config/              # Configuration files
├── includes/            # PHP includes and utilities
├── panels/              # Different user panels (admin, farmer, buyer)
├── .gitignore           # Git ignore file
└── README.md            # Project documentation
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
