# Tinhouse Engineering - Project Architecture & Features

## Project Overview

**Tinhouse Engineering** is a comprehensive project management system designed for sheet-metal and steel-structure engineering firms. It provides end-to-end management of customers, projects, quotations, materials, equipment, workforce, and financial records.

**Technology Stack**: Laravel 12 + React/Inertia + PostgreSQL 18 (Docker-based development environment)

---

## Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Backend Framework** | Laravel | 12.0 |
| **Frontend Framework** | React + Inertia.js | 18.2 + 2.0 |
| **Database** | PostgreSQL | 18 (Alpine) |
| **Caching & Queues** | Redis | 7 |
| **File Storage** | MinIO (S3-compatible) | Latest |
| **Build Tool** | Vite | 7.0 |
| **Styling** | Tailwind CSS | 3.2 |
| **UI Components** | Headless UI | 2.0 |
| **PDF Generation** | Browsershot (Chromium) | 5.3 |
| **Authentication** | Laravel Sanctum + Breeze | 4.0 + 2.4 |
| **Icons** | Lucide React | 1.14 |
| **Validation** | CVA (Class Variance Authority) | 0.7 |

---

## Core Features & Modules

### 1. Customer Management
**Models**: `Customer`, `CustomerContact`

- Maintain customer master records
- Store multiple contact persons per customer
- Track customer information and relationships
- Support for customer segmentation

### 2. Project Management
**Models**: `Project`, `ProjectChangeOrder`

- Create and track engineering projects
- Project status lifecycle management
- GPS-based location tracking (latitude/longitude)
- Project timeline (start_date, end_date)
- Financial tracking (contract_amount, estimated_cost, actual_cost)
- Project assignment to work crews and managers
- Change order management for scope modifications

### 3. Quotation Management
**Models**: `Quotation`, `QuotationItem`, `QuotationTemplate`, `QuotationTemplateItem`

- Generate quotations from templates
- Track quotation lifecycle (draft, sent, confirmed, locked, voided)
- Customer confirmation workflow
- Quotation-to-project mapping
- Template management for standardized pricing
- Item-level line-item management
- Quotation locking and reopening capabilities
- Void tracking with reasons

### 4. Material & Inventory Management
**Models**: `Material`, `MaterialCategory`, `InventoryTransaction`

- Maintain material master database
- Organize materials by categories
- Track inventory transactions (receipt, issuance, adjustment)
- Real-time inventory level monitoring
- Material costing and valuation

### 5. Equipment Management
**Models**: `Equipment`, `EquipmentCategory`, `EquipmentTransaction`

- Maintain equipment inventory
- Categorize equipment types
- Track equipment transaction history
- Equipment maintenance and usage logs
- Equipment assignment to projects/work crews

### 6. Workforce Management
**Models**: `Worker`, `WorkCrew`, `AttendanceRecord`

- Employee master records with profiles
- Work crew organization and assignment
- Attendance tracking
- Work hours reporting (`WorkHoursReport`)
- Crew assignment to projects and dispatches

### 7. Dispatch Management
**Models**: `Dispatch`, `dispatch_worker` (pivot table)

- Create dispatch orders for on-site work
- Assign workers to dispatches (many-to-many)
- Track dispatch status and completion
- Link dispatches to projects

### 8. Progress Tracking
**Models**: `ProgressLog`, `ProgressPhoto`

- Record daily project progress
- Photo documentation of work progress
- Progress timeline visualization
- Status updates tied to dispatches/projects

### 9. Purchase Management
**Models**: `PurchaseOrder`, `PurchaseOrderItem`, `Supplier`

- Create and manage purchase orders
- Supplier relationship management
- Purchase order item tracking
- PO-to-project linkage
- Supplier history and performance

### 10. Financial Management
**Models**: `FinancialRecord`

- Transaction recording (income, expenses, payments)
- Project financial tracking
- Payment status management
- Financial reporting capabilities
- Link to projects and quotations

### 11. User & Role Management
**Models**: `User`, `Role`, `Capability`

- User account management
- Role-based access control
- Capability-based authorization (`CapabilityAuthorizer`)
- Data scope isolation (`DataScope`)
- Multi-tenant support (`Tenant`)

### 12. System & Audit
**Models**: `SystemSetting`, `ActivityLog`

- System configuration management
- Automatic activity logging (via `ActivityLogObserver`)
- Audit trail for data changes
- Regulatory compliance tracking

---

## Application Structure

### Directory Layout

```
app/
├── Actions/                    # Business logic operations
│   ├── Dispatches/
│   ├── Inventory/
│   ├── Payments/
│   ├── Projects/
│   └── Quotations/
├── Auth/                       # Authentication & Authorization
│   ├── CapabilityAuthorizer.php
│   └── DataScope.php
├── Http/
│   ├── Controllers/            # 25+ RESTful controllers
│   ├── Middleware/
│   └── Requests/               # Form validation
├── Jobs/                       # Queue jobs
├── Models/                     # 20+ Eloquent models
├── Observers/                  # Event listeners (ActivityLog)
├── Providers/
│   └── AppServiceProvider.php
└── Services/                   # Business service layer

database/
├── migrations/                 # Schema definitions
├── seeders/                    # Sample data
└── factories/                  # Model factories

routes/
├── web.php                     # Web routes (CRUD endpoints)
├── auth.php                    # Authentication routes
└── console.php                 # Artisan commands

resources/
├── js/
│   ├── Pages/                  # Inertia page components
│   ├── Components/             # Reusable React components
│   ├── Hooks/                  # Custom React hooks
│   └── lib/                    # Utility functions
└── css/                        # Tailwind styles

tests/
├── Feature/                    # Feature tests
└── Unit/                       # Unit tests
```

---

## Database Schema - MVP Flow

### Entity Relationship Flow

```
Customer
  ├─ CustomerContact
  └─ Project
      ├─ Quotation
      │   └─ QuotationItem (references Material)
      ├─ ProgressLog
      ├─ ProgressPhoto
      ├─ Dispatch
      │   └─ Worker (many-to-many via dispatch_worker)
      ├─ EquipmentTransaction
      ├─ InventoryTransaction
      ├─ PurchaseOrder
      │   └─ PurchaseOrderItem
      └─ FinancialRecord

WorkCrew
  └─ Worker
      └─ AttendanceRecord

Material
  └─ MaterialCategory

Equipment
  └─ EquipmentCategory

Supplier
  └─ PurchaseOrder

Quotation
  ├─ QuotationTemplate
  │   └─ QuotationTemplateItem
  └─ Customer
```

### Core Tables

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `customers` | Customer master data | 1:N with projects, quotations |
| `customer_contacts` | Contact persons | N:1 with customers |
| `projects` | Project records | 1:N with quotations, dispatches, progress logs |
| `quotations` | Quote documents | 1:N with items, 1:1 with quotation_templates |
| `quotation_items` | Quote line items | 1:N with quotations |
| `quotation_templates` | Reusable quote templates | 1:N with template items |
| `materials` | Material inventory | 1:N with inventory_transactions |
| `material_categories` | Material classification | 1:N with materials |
| `inventory_transactions` | Stock movements | N:1 with materials, projects |
| `equipment` | Equipment assets | 1:N with equipment_transactions |
| `equipment_categories` | Equipment classification | 1:N with equipment |
| `equipment_transactions` | Equipment movements | N:1 with equipment, projects |
| `work_crews` | Team groupings | 1:N with workers, 1:N with projects |
| `workers` | Employee records | 1:N with attendance, N:N with dispatches |
| `attendance_records` | Attendance tracking | N:1 with workers |
| `dispatches` | Work orders | N:N with workers, N:1 with projects |
| `progress_logs` | Project progress | N:1 with projects |
| `progress_photos` | Visual documentation | N:1 with projects |
| `purchase_orders` | Supplier orders | 1:N with items, N:1 with suppliers |
| `purchase_order_items` | Order line items | N:1 with purchase_orders |
| `suppliers` | Supplier master data | 1:N with purchase_orders |
| `financial_records` | Financial transactions | N:1 with projects |
| `users` | User accounts | N:1 with roles, N:1 with tenants |
| `roles` | Access control roles | 1:N with users |
| `capabilities` | Permission definitions | 1:N with roles |
| `activity_logs` | Audit trail | Polymorphic across all models |
| `tenants` | Multi-tenant isolation | 1:N with users |
| `system_settings` | System configuration | Global settings |

---

## Controllers & Routes

### 25+ REST Controllers

| Resource | Controller | Endpoints |
|----------|-----------|-----------|
| Dashboard | `DashboardController` | Index |
| Customers | `CustomerController` | Index, Create, Store, Show, Edit, Update, Delete |
| Projects | `ProjectController` | Index, Create, Store, Show, Edit, Update, Delete |
| Quotations | `QuotationController` | Index, Create, Store, Show, Edit, Update, Delete |
| Materials | `MaterialController` | Index, Create, Store, Show, Edit, Update, Delete |
| Equipment | `EquipmentController` | Index, Create, Store, Show, Edit, Update, Delete |
| Work Crews | `WorkCrewController` | Index, Create, Store, Show, Edit, Update, Delete |
| Workers | `WorkerController` | Index, Create, Store, Show, Edit, Update, Delete |
| Dispatches | `DispatchController` | Index, Create, Store, Show, Edit, Update, Delete |
| Financial Records | `FinancialRecordController` | Index, Create, Store, Show, Edit, Update, Delete |
| Purchase Orders | `PurchaseOrderController` | Index, Create, Store, Show, Edit, Update, Delete |
| Suppliers | `SupplierController` | Index, Create, Store, Show, Edit, Update, Delete |
| Quotation Templates | `QuotationTemplateController` | Index, Create, Store, Show, Edit, Update, Delete |
| Roles | `RoleController` | Index, Create, Store, Show, Edit, Update, Delete |
| Users | `UserController` | Index, Create, Store, Show, Edit, Update, Delete |
| Equipment Categories | `EquipmentCategoryController` | Index, Create, Store, Show, Edit, Update, Delete |
| Progress Logs | `ProgressLogController` | Index, Create, Store, Show, Edit, Update, Delete |
| Attendance Records | `AttendanceRecordController` | Index, Create, Store, Show, Edit, Update, Delete |
| Project Change Orders | `ProjectChangeOrderController` | Index, Create, Store, Show, Edit, Update, Delete |
| Inventory Transactions | `InventoryTransactionController` | Index, Create, Store, Show, Edit, Update, Delete |
| Equipment Transactions | `EquipmentTransactionController` | Index, Create, Store, Show, Edit, Update, Delete |
| Activity Logs | `ActivityLogController` | Index (view-only) |
| Work Hours Report | `WorkHoursReportController` | Report generation |
| System Settings | `SystemSettingController` | Configuration management |
| Profile | `ProfileController` | User profile management |
| Auth | `Auth/*` | Login, registration, password reset |

### Route Protection

Routes are protected using capability middleware:
```php
Route::get('customers', [CustomerController::class, 'index'])
    ->middleware('capability:customers.view.tenant');
```

---

## Security & Multi-Tenancy

### Authentication
- **Laravel Sanctum**: Token-based API authentication
- **Laravel Breeze**: Pre-built authentication scaffolding
- Session-based web authentication

### Authorization
- **Capability-Based Access Control**: Fine-grained permissions
- **DataScope**: Automatic data filtering by tenant/user
- **CapabilityAuthorizer**: Centralized authorization logic
- **Role-Based Assignment**: Roles contain multiple capabilities

### Multi-Tenancy
- **Tenant Model**: Isolate data per organization
- **Automatic Scoping**: DataScope middleware filters queries
- **Tenant Context**: Available throughout request lifecycle

### Example Authorization
```
User → Role → Capability
       ├─ customers.view.tenant
       ├─ customers.create.tenant
       ├─ projects.view.tenant
       └─ ...
```

---

## Development Workflow

### MVP Data Flow

```
Customer → Project → Quotation → Materials/Inventory → Dispatch → Payment Tracking
```

### Business Process

1. **Customer Intake**: Create customer records with contacts
2. **Project Setup**: Create project linked to customer
3. **Quotation**: Generate quote from template for customer approval
4. **Planning**: Assign materials, equipment, and work crews
5. **Execution**: Create dispatches, track attendance
6. **Documentation**: Record progress via logs and photos
7. **Costing**: Track actual expenses via inventory/equipment transactions
8. **Billing**: Record financial transactions, reconcile against quotation
9. **Closure**: Archive project and generate final reports

---

## Docker Services

| Service | Port | Purpose | Technology |
|---------|------|---------|-----------|
| **app** | 8080 | Web application & API | Nginx + PHP 8.4 |
| **node** | 5173 | Frontend dev server | Vite + Node.js 24 |
| **postgres** | 5432 | Primary database | PostgreSQL 18 |
| **redis** | 6379 | Cache, queue, sessions | Redis 7 |
| **minio** | 9001 | File storage console | MinIO (S3-compatible) |
| **mailpit** | 8025 | Email testing | Mailpit |
| **adminer** | 8081 | Database browser | Adminer |

### Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_DATABASE` | tinhouse | PostgreSQL database name |
| `DB_USERNAME` | tinhouse | Database user |
| `DB_PASSWORD` | secret | Database password |
| `APP_PORT` | 8080 | Laravel app port |
| `VITE_FORWARD_PORT` | 5173 | Vite dev server port |
| `REDIS_FORWARD_PORT` | 6379 | Redis port |
| `MINIO_FORWARD_PORT` | 9000 | MinIO API port |
| `MINIO_CONSOLE_FORWARD_PORT` | 9001 | MinIO console port |

---

## Key Features & Capabilities

### Data Management
- ✅ RESTful CRUD operations for all resources
- ✅ Relationship management (customer → projects → quotations)
- ✅ Document versioning for quotations
- ✅ Change tracking and audit logs
- ✅ Soft deletes for data recovery

### Reporting & Analytics
- ⏳ Work hours reporting by crew/worker
- ⏳ Financial reconciliation reports
- ⏳ Project progress dashboards
- ⏳ Inventory movement reports

### Integration Points
- 📄 PDF generation via Browsershot (invoices, quotations)
- 📧 Email notifications via Mailpit (development)
- 📦 S3-compatible file storage via MinIO
- 💾 Redis-backed queue jobs for async processing

### Frontend (React/Inertia)
- ✅ Component-based UI architecture
- ✅ Form validation with server feedback
- ✅ Real-time data synchronization
- ✅ Responsive Tailwind CSS styling
- ✅ Dark mode ready

---

## Development Roadmap

### Current Status (MVP Phase)
- ✅ Core data models implemented
- ✅ Database schema designed
- ✅ Authentication & authorization framework
- ✅ Controller scaffolding complete
- ⏳ Inertia CRUD pages for customers & projects

### Next Phases
1. **Phase 2**: Complete Inertia UI for all resources
2. **Phase 3**: Advanced reporting dashboards
3. **Phase 4**: Mobile app support
4. **Phase 5**: AI-powered quotation suggestions
5. **Phase 6**: Automated workflows & notifications

---

## Getting Started

### Quick Setup
```bash
# Clone and setup
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Frontend
docker compose run --rm node npm install
docker compose run --rm --service-ports node npm run dev -- --host 0.0.0.0
```

### Default Credentials
- **Email**: `admin@example.com`
- **Password**: `password`

### Useful Commands
```bash
# Laravel commands
docker compose exec app php artisan tinker                    # REPL
docker compose exec app php artisan migrate:fresh --seed      # Reset database
docker compose exec app php artisan queue:work                # Process jobs
docker compose exec app php artisan storage:link              # Create storage symlink

# Testing
docker compose exec app php artisan test                       # Run tests

# Frontend
docker compose run --rm node npm run build                     # Build for production
docker compose run --rm node npm run dev                       # Dev with HMR
```

---

## API Endpoints

All endpoints follow RESTful conventions:

```
GET    /api/{resource}              # List
POST   /api/{resource}              # Create
GET    /api/{resource}/{id}         # Show
PUT    /api/{resource}/{id}         # Update
DELETE /api/{resource}/{id}         # Delete
```

### Authentication Header
```
Authorization: Bearer {token}
Content-Type: application/json
```

---

## Performance Optimizations

- **Eager Loading**: Relationships loaded to prevent N+1 queries
- **Pagination**: List endpoints paginated (default 15 per page)
- **Caching**: Redis for cache, session storage
- **Queue Jobs**: Heavy operations run asynchronously
- **Database Indexing**: Foreign keys and commonly queried fields indexed

---

## Compliance & Standards

- **PHP Standards**: PSR-4 autoloading, PSR-12 coding style
- **Laravel Conventions**: Eloquent ORM, migrations, seeders
- **Git Workflow**: Feature branches, pull requests
- **Testing**: PHPUnit for backend, Jest/React Testing Library for frontend
- **Code Quality**: Laravel Pint for formatting, Phpstan for static analysis

---

## Deployment Considerations

- Docker containerization for consistency
- Environment-based configuration (.env)
- Database migrations for schema versioning
- Asset compilation (npm run build)
- Health checks on all services
- Volume mounting for persistent data

---

**Last Updated**: May 2026
**Framework Versions**: Laravel 12, React 18, PostgreSQL 18
