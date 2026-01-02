# 🛒 Enterprise Retail POS & Inventory Management System

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white" alt="Vue.js 3">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2">
  <img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
</p>

## 📋 Project Overview

An **enterprise-level Point of Sale (POS)** and **Inventory Management System** built for multi-location retail operations. This system handles real-time transactions, stock management across multiple locations, and complex approval workflows.

**Role:** Full-Stack Developer  
**Industry:** Retail / Merchandising

---

## 🛠️ Technology Stack

### Backend
| Technology | Purpose |
|-----------|---------|
| **Laravel 11** | Core PHP Framework |
| **PHP 8.2+** | Server-side Language |
| **MySQL** | Relational Database |
| **Laravel Fortify** | Authentication System |
| **Spatie Permission** | Role-Based Access Control |
| **Laravel Sanctum** | API Token Authentication |

### Frontend
| Technology | Purpose |
|-----------|---------|
| **Vue.js 3** | Reactive UI Framework |
| **Inertia.js** | Modern SPA Architecture |
| **Tailwind CSS** | Utility-First Styling |
| **Ant Design Vue** | Enterprise UI Components |

### Integrations
- **Barcode/QR:** Hardware scanner support
- **Export:** Excel & PDF generation (DomPDF, Maatwebsite Excel)
- **Image Processing:** Server-side image handling (Intervention Image)

---

## 📊 Project Scale

| Metric | Scale |
|--------|-------|
| **Controllers** | 25+ |
| **Database Models** | 40+ |
| **Database Tables** | 50+ |
| **API Endpoints** | 300+ |
| **User Roles** | 8 distinct roles |

---

## ✨ Key Features

### 1. 🛒 Point of Sale (POS) System
Real-time cashier system with barcode scanning integration.

- **Dual Architecture:** Standard SPA for CRUD, optimized API for fast transactions
- **Performance Target:** < 100ms response for barcode scanning
- **Real-time stock validation** to prevent overselling
- **Multi-cashier support** with session management

### 2. 📦 Multi-Location Inventory Management
Stock management across multiple store locations.

- **Location-based stock tracking**
- **Stock movement logging** with full audit trail
- **Batch input operations** for efficiency
- **Custom stock calculation algorithms**

### 3. 📊 Bulk Stock Opname System
Mass stock-taking with scheduling and notifications.

- **Scheduled stock opname** with reminder system
- **Bulk scanning** with batch processing
- **Late detection** and tracking
- **Header-detail pattern** for batch records

### 4. ✅ Multi-Level Approval Workflow
Approval system for critical operations.

**Covered Operations:**
- Product modifications (create/edit/delete)
- Stock adjustments
- Category changes
- Transaction deletions
- Inventory withdrawals

**Implementation:**
- Separate approval tables per entity type
- Status flow: Pending → Approved/Rejected
- Role-based approval authority
- Full audit trail

### 5. 🔐 Role-Based Access Control (RBAC)
Granular authorization system with 8 distinct roles.

| Role | Access Level |
|------|-------------|
| IT Admin | Full system access |
| Executive | Reports + Final approvals |
| Supervisor | Monitoring + Approvals |
| Cashier | POS + Daily operations |
| Supplier | Product & Stock input |
| Warehouse | Inventory management |
| Finance | Financial reports |
| Limited | Restricted access |

### 6. 📈 Comprehensive Reporting
Multi-format reporting with export capabilities.

- Sales reports (daily/monthly/yearly)
- Inventory reports per location
- Best selling products analysis
- Stock movement reports
- **Export formats:** Excel & PDF

### 7. 📱 Barcode/QR Integration
Hardware scanner support for various use cases.

- Product barcode generation
- POS barcode scanning
- Stock opname QR scanning
- Batch barcode printing

### 8. 💬 Support Ticket System
Internal complaint/issue tracking.

- Ticket creation with descriptions
- Chat thread per ticket
- Status tracking
- Resolution workflow

---

## 🏗️ Architecture Decisions

### Dual SPA Architecture
**Challenge:** Standard SPA framework overhead for high-frequency POS operations.  
**Solution:** Hybrid approach - full Inertia.js framework for complex pages, lightweight vanilla API calls for speed-critical operations.  
**Result:** Sub-100ms response for critical user flows.

### Approval Workflow Pattern
**Challenge:** Data integrity for destructive operations.  
**Solution:** Dedicated approval tables with status-based workflow.  
**Result:** Full auditability, role-based control, reversible operations.

### Multi-Location Architecture
**Challenge:** Multiple business locations with separate inventory.  
**Solution:** Location ID pattern on all inventory-related tables.  
**Result:** Accurate per-location tracking with centralized management.

---

## 🔧 Technical Challenges Solved

| Challenge | Solution |
|-----------|----------|
| **Query Performance** | Eager loading, selective columns, database indexing |
| **Real-Time Inventory** | Batch calculation methods, transaction isolation |
| **Hardware Compatibility** | Configurable debounce timing, auto-focus management |
| **Cache Invalidation** | Multiple cache busting strategies (file versioning, CDN versioning) |

---

## 📈 Performance Targets

| Operation | Target |
|-----------|--------|
| Initial Page Load | < 500ms |
| Barcode Scan → Response | < 100ms |
| Add to Cart | < 80ms |
| Transaction Completion | < 200ms |

---

## 🔒 Security Implementation

| Feature | Implementation |
|---------|----------------|
| CSRF Protection | Framework middleware |
| XSS Prevention | Template escaping |
| SQL Injection | ORM-based queries |
| Password Security | Bcrypt hashing |
| API Security | Token-based auth (Sanctum) |
| Authorization | Permission-based middleware |
| Rate Limiting | Login protection |

---

## 📁 Code Samples

> **Note:** These are conceptual implementations demonstrating patterns and approaches. Written for educational/portfolio purposes.

See the `/samples` directory for example implementations:
- [`CartService.php`](./samples/services/CartService.php) - Stock validation & cart management
- [`ApprovalWorkflow.php`](./samples/services/ApprovalWorkflow.php) - Multi-level approval pattern
- [`BarcodeScanner.vue`](./samples/frontend/BarcodeScanner.vue) - Hardware scanner integration

---

## 💡 Key Learnings

1. **Performance vs DX Trade-offs:** Sometimes hybrid approaches are needed to balance developer experience with user experience.

2. **Approval Systems Complexity:** Enterprise apps often need multi-level approvals - plan architecture early.

3. **Hardware Integration:** Physical device integration requires flexible configuration to accommodate variations.

4. **Inventory Systems:** Real-time accuracy requires careful handling of race conditions and transaction isolation.

---

## 🎯 Skills Demonstrated

- ✅ Full-Stack Development (Laravel + Vue.js)
- ✅ Database Design (Complex relational schema)
- ✅ API Development (RESTful architecture)
- ✅ Security (Authentication, authorization, data protection)
- ✅ Performance Optimization (Query optimization, caching)
- ✅ System Architecture (Scalable, maintainable code)
- ✅ Hardware Integration (Barcode/QR scanner support)

---

## 📄 License

This portfolio documentation is available for educational purposes.

---

*Built with ❤️ using Laravel, Vue.js, and modern web technologies.*
