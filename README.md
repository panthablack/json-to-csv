# JSON to CSV Converter

A modern Laravel + Vue.js application for transforming JSON data into customizable CSV exports with advanced field mapping and data transformation capabilities.

## 🚀 Features

- **JSON File Upload**: Drag & drop or browse to upload JSON files
- **Advanced Field Mapping**: Map JSON fields to CSV columns with dot notation support
- **Data Transformation**: Custom JavaScript callbacks for complex data processing
- **Multiple Export Formats**: Generate single CSV files or bulk ZIP archives
- **Real-time Preview**: Preview CSV output before export
- **Configuration Management**: Save and reuse CSV configurations
- **User Authentication**: Secure user accounts with Laravel Fortify

## 🛠 Tech Stack

- **Backend**: Laravel 12 with PHP 8.4
- **Frontend**: Vue 3 with TypeScript
- **UI Framework**: Tailwind CSS v4 + reka-ui components
- **Database**: MySQL 8.0
- **Authentication**: Laravel Fortify
- **Testing**: Pest PHP
- **Build Tools**: Vite
- **Containerization**: Docker & Docker Compose

## 📋 Prerequisites

- Docker & Docker Compose
- Or alternatively: PHP 8.4+, Node.js 20+, MySQL 8.0+

## 🚀 Quick Start with Docker

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd json-to-csv
   ```

2. **Start the application**
   ```bash
   docker-compose up -d
   ```

3. **Access the application**
   - Application: http://localhost:8000
   - Vite Dev Server: http://localhost:5173

The Docker setup includes:
- Laravel application with PHP 8.4-FPM
- MySQL 8.0 database
- Node.js for Vite development server
- Auto-installation of dependencies

## 🔧 Manual Installation

1. **Install PHP dependencies**
   ```bash
   composer install
   ```

2. **Install Node.js dependencies**
   ```bash
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start development servers**
   ```bash
   # Start all development services
   composer run dev

   # Or start individually
   php artisan serve
   npm run dev
   ```

## 🎯 Usage

### 1. Upload JSON Data
- Navigate to the upload page
- Drag & drop or select a JSON file
- The system will parse and validate the structure

### 2. Configure CSV Mapping
- Define field mappings using simple names or dot notation
- Example mappings:
  ```json
  {
    "name": "name",
    "email": "email",
    "age": "profile.age",
    "city": "profile.city",
    "street": "profile.address.street"
  }
  ```

### 3. Add Custom Transformations
- Use JavaScript callbacks for complex data processing:
  ```javascript
  {
    "formatted_date": (data) => new Date(data.created_at).toLocaleDateString(),
    "status": (data) => data.is_active ? 'Active' : 'Inactive',
    "full_address": (data) => `${data.profile.address.street}, ${data.profile.city}`
  }
  ```

### 4. Export Data
- Preview the CSV output
- Download single CSV file
- Generate bulk ZIP archive for multiple configurations

## 📁 Project Structure

```
├── app/
│   ├── Http/Controllers/
│   │   ├── JsonProcessorController.php    # JSON upload & parsing
│   │   ├── CsvConfigController.php        # CSV configuration
│   │   └── CsvExportController.php        # Export functionality
│   └── Models/
│       ├── JsonData.php                   # JSON data storage
│       └── CsvConfiguration.php           # CSV config persistence
├── resources/js/
│   ├── pages/
│   │   ├── Dashboard.vue                  # Main dashboard
│   │   ├── JsonUpload.vue                 # File upload
│   │   ├── CsvConfiguration.vue           # CSV setup
│   │   └── Export.vue                     # Export management
│   └── components/                        # Reusable Vue components
├── examples/
│   └── users.json                         # Sample data
└── docker-compose.yaml                    # Docker configuration
```

## 🔗 API Endpoints

### JSON Processing
- `POST /api/json` - Upload JSON file
- `GET /api/json` - List user's JSON files
- `GET /api/json/{id}` - Retrieve JSON data
- `DELETE /api/json/{id}` - Delete JSON file

### CSV Configuration
- `GET /api/csv-config` - List configurations
- `POST /api/csv-config` - Save CSV configuration
- `GET /api/csv-config/{id}` - Load configuration
- `PUT /api/csv-config/{id}` - Update configuration
- `DELETE /api/csv-config/{id}` - Delete configuration

### Export & Download
- `GET /api/export/single/{configId}` - Download single CSV
- `POST /api/export/multiple` - Generate bulk ZIP
- `GET /download/{filename}` - Download export file

## 🧪 Testing

```bash
# Run all tests
composer run test

# Run specific test suite
php artisan test --filter=JsonProcessorTest

# Frontend linting
npm run lint

# Type checking
npm run format:check
```

## 🐳 Docker Commands

```bash
# Start development environment
docker-compose up -d

# View logs
docker-compose logs -f app

# Execute commands in container
docker-compose exec app php artisan migrate
docker-compose exec app composer install

# Stop environment
docker-compose down
```

## 📝 Example Data

The `examples/users.json` file contains sample data demonstrating:
- Nested objects (`profile.age`, `profile.city`)
- Deeply nested objects (`profile.address.street`)
- Arrays (`tags`)
- Various data types (strings, numbers, booleans, dates)

## 🔒 Security Features

- File upload validation and sanitization
- JSON parsing limits and timeout protection
- User authentication for saved configurations
- Rate limiting on API endpoints
- Secure file storage with Laravel filesystem

## 🚀 Performance

- Chunked processing for large JSON files
- Database indexing for fast configuration lookup
- Frontend asset optimization with Vite
- Lazy loading of configuration options
- Efficient CSV generation with League CSV

## 📦 Key Dependencies

### Backend
- `league/csv` - CSV generation and manipulation
- `laravel/fortify` - Authentication scaffolding
- `inertiajs/inertia-laravel` - Frontend integration

### Frontend
- `@inertiajs/vue3` - Laravel-Vue integration
- `reka-ui` - UI component library
- `tailwindcss` - CSS framework
- `lucide-vue-next` - Icon library
- `@vueuse/core` - Vue composition utilities

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🆘 Support

For issues and questions:
- Create an issue on GitHub
- Review the example data and configurations

---

**Built with ❤️ using Laravel and Vue.js**