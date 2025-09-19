# Budget App - Receipt Manager

A modern Laravel-based budget application with intelligent receipt processing capabilities. This app allows users to upload receipts via camera or file upload, automatically extracts text using OCR, and intelligently categorizes expenses using AI.

## 🚀 Features

### Core Functionality
- **Smart Receipt Processing**: Upload receipts via mobile camera or file upload
- **OCR Integration**: Automatic text extraction from images and PDFs
- **AI-Powered Categorization**: Intelligent expense categorization using OpenAI
- **Line Item Extraction**: Automatic parsing of individual items from receipts
- **Dynamic Category Management**: Auto-creation of new categories and subcategories
- **Real-time Processing**: Background job processing with status tracking

### User Interface
- **Mobile-First Design**: Optimized for mobile camera capture
- **Modern React Frontend**: Built with Inertia.js and React
- **Responsive Design**: Tailwind CSS for beautiful, responsive UI
- **Real-time Updates**: Live status updates during processing

### Technical Features
- **Laravel 12**: Latest Laravel framework with modern PHP 8.2+
- **Inertia.js**: Seamless SPA experience without API complexity
- **React 18**: Modern React with hooks and functional components
- **Queue Processing**: Background job processing for OCR and AI tasks
- **File Storage**: Secure file handling with public access
- **Authentication**: Laravel Breeze with Google OAuth integration

## 🛠️ Technology Stack

### Backend
- **Laravel 12** - PHP web framework
- **PHP 8.2+** - Modern PHP with latest features
- **MySQL 8.0** - Primary database
- **Laravel Sanctum** - API authentication
- **Laravel Socialite** - OAuth integration
- **Laravel Queues** - Background job processing

### Frontend
- **React 18** - Modern JavaScript library
- **Inertia.js** - SPA without API complexity
- **Tailwind CSS** - Utility-first CSS framework
- **Headless UI** - Accessible UI components
- **Vite** - Fast build tool and dev server

### External Services
- **OCR Service** - Text extraction from images/PDFs
- **OpenAI API** - AI-powered categorization and parsing
- **Google OAuth** - Social authentication

## 📋 Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- MySQL 8.0+
- Docker and Docker Compose (optional, for containerized development)

## 🚀 Installation

### Option 1: Using Docker (Recommended)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd budget-app
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure environment variables**
   Edit `.env` file with your configuration:
   ```env
   # Database
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=budget_app
   DB_USERNAME=root
   DB_PASSWORD=

   # OCR Service
   OCR_NEXT_SERVER=http://ocr-next-api
   OCR_NEXT_API_KEY=your_api_key
   OCR_NEXT_API_TOKEN=your_api_token

   # OpenAI
   OPENAI_API_KEY=your_openai_api_key

   # Google OAuth
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   ```

5. **Start with Docker**
   ```bash
   ./vendor/bin/sail up -d
   ```

6. **Run migrations and seeders**
   ```bash
   ./vendor/bin/sail artisan migrate --seed
   ```

7. **Start development servers**
   ```bash
   composer run dev
   ```

### Option 2: Local Development

1. **Clone and install dependencies**
   ```bash
   git clone <repository-url>
   cd budget-app
   composer install
   npm install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup**
   ```bash
   php artisan migrate --seed
   ```

4. **Start development servers**
   ```bash
   # Terminal 1: Laravel server
   php artisan serve

   # Terminal 2: Vite dev server
   npm run dev

   # Terminal 3: Queue worker
   php artisan queue:work
   ```

## 📁 Project Structure

```
budget-app/
├── app/
│   ├── Http/Controllers/     # API controllers
│   ├── Jobs/                 # Background jobs
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic services
│   └── Providers/            # Service providers
├── database/
│   ├── migrations/           # Database migrations
│   └── seeders/             # Database seeders
├── resources/
│   ├── js/
│   │   ├── Components/       # Reusable React components
│   │   ├── Layouts/          # Layout components
│   │   └── Pages/            # Page components
│   └── css/                  # Stylesheets
├── routes/                   # Route definitions
├── storage/                  # File storage
└── tests/                    # Test files
```

## 🗄️ Database Schema

### Core Models

**Categories**
- Hierarchical category system (parent/child relationships)
- Auto-created categories from AI processing
- Pre-seeded with Groceries and Building categories

**Receipts**
- File storage and metadata
- OCR text and processing status
- Vendor, currency, and total amount
- Category and subcategory associations

**Receipt Items**
- Individual line items from receipts
- Quantity, unit price, and total calculations
- Linked to parent receipts

## 🔧 Configuration

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `OCR_NEXT_SERVER` | OCR service base URL | Yes |
| `OCR_NEXT_API_KEY` | OCR service API key | Yes |
| `OCR_NEXT_API_TOKEN` | OCR service bearer token | Yes |
| `OPENAI_API_KEY` | OpenAI API key for AI processing | Yes |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | Yes |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret | Yes |

### File Upload Limits

- Maximum file size: 15MB
- Supported formats: JPG, JPEG, PNG, HEIC, WEBP, PDF
- Storage location: `storage/app/public/receipts/`

## 🎯 Usage

### Uploading Receipts

1. **Mobile Camera Capture**
   - Tap "Scan with Camera" button
   - Capture receipt directly with device camera
   - Automatic file validation and upload

2. **File Upload**
   - Tap "Upload Photo/PDF" button
   - Select file from device storage
   - Preview before submission

### Processing Flow

1. **Upload** → File validation and storage
2. **OCR Processing** → Text extraction from image/PDF
3. **AI Analysis** → Category classification and item extraction
4. **Data Persistence** → Save structured data to database
5. **Status Updates** → Real-time processing status

### Managing Receipts

- **View All Receipts**: Paginated list with status badges
- **Receipt Details**: View OCR text, edit categories, modify items
- **Retry Processing**: Retry failed receipts
- **File Access**: Direct file viewing and download

## 🧪 Testing

```bash
# Run PHP tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature
```

## 🚀 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Configure production database
- [ ] Set up file storage (S3, etc.)
- [ ] Configure queue workers
- [ ] Set up SSL certificates
- [ ] Configure caching (Redis/Memcached)
- [ ] Set up monitoring and logging

### Queue Workers

For production, ensure queue workers are running:

```bash
# Using Supervisor (recommended)
php artisan queue:work --daemon

# Or using Laravel Horizon (if installed)
php artisan horizon
```

## 📊 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/receipts` | List all receipts |
| POST | `/receipts` | Upload new receipt |
| GET | `/receipts/{id}` | Show receipt details |
| PATCH | `/receipts/{id}` | Update receipt data |
| POST | `/receipts/{id}/retry` | Retry processing |
| GET | `/receipts/{id}/file` | Access receipt file |
| GET | `/categories` | List categories |

## 🔒 Security Features

- **File Validation**: MIME type and size restrictions
- **Rate Limiting**: Upload endpoint protection
- **Authentication**: Laravel Sanctum + OAuth
- **CSRF Protection**: Built-in Laravel protection
- **Input Sanitization**: Automatic data cleaning
- **Secure Storage**: Protected file access

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:

- Create an issue in the repository
- Check the documentation in `/docs` folder
- Review the Laravel documentation for framework-specific questions

## 🔄 Changelog

### Version 1.0.0
- Initial release with core receipt processing functionality
- OCR integration with external service
- AI-powered categorization using OpenAI
- Mobile-first responsive design
- Background job processing
- Google OAuth authentication

---

**Built with ❤️ using Laravel, React, and modern web technologies.**