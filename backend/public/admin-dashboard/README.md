# Super Admin Dashboard - Ajo Platform

A comprehensive, dynamic admin control center with FULL platform management capabilities. This is NOT a static dashboard - it's a powerful super admin interface that gives you complete control over everything!ilities.

## Features

### 🎯 Dashboard Overview
- Real-time statistics and metrics
- User analytics (total, active, suspended, KYC status)
- Group performance metrics
- Transaction volume and success rates
- System health monitoring
- Pending actions alerts

### 👥 User Management
- View all users with advanced filtering
- Search users by name, email, or phone
- Filter by status (active, suspended, inactive)
- Filter by KYC status (verified, pending, rejected)
- View detailed user profiles
- Suspend/activate user accounts
- Monitor wallet balances
- Export user data to CSV

### 🆔 KYC Approvals
- View all pending KYC submissions
- Review KYC documents
- Amissions with reasons
- Real-time badge notifications
- Track verification history

### 🏢 Group Management
iltering
- Search groups by name
- Filter by status (pending, active, completed, cancelled)
- View detailed group information
- Monitor group members and contributions
- Track group performance
- Export group data

### 💰 Withdrawal Management
- View all pending withdrawal requests
- Review withdrawal details
- Approve or reject withdrawals
- Monitor bank account information
- Real-time notifications
- Track withdrawal history

## Features

### 🎯 Dashboard Overview
- Real-time statistics and metrics
- User analytics (total, active, suspended, KYC status)
- Group performance metrics
- Transaction volume and success rates
- System health monitoring
- Pending actions alerts with badges

### 👥 User Management (FULL CONTROL)
- View ALL users with advanced filtering
- Search users by name, email, or phone
- Filter by status (active, suspended, inactive)
- Filter by KYC status (verified, pending, rejected)
- View detailed user profiles with complete information
- **Suspend user accounts** with reason tracking
- **Activate suspended accounts**
- Monitor wallet balances
- Export user data to CSV
- Bulk operations support

### 🆔 KYC Approvals (COMPLETE WORKFLOW)
- View all pending KYC submissions
- Review KYC documents directly
- **Approve KYC submissions** instantly
- **Reject KYC** with detailed reasons
- Real-time badge notifications
- Track verification history
- Document preview

### 🏢 Group Management (FULL VISIBILITY)
- View ALL groups with status filtering
- Search groups by name
- Filter by status (pending, active, completed, cancelled)
- View detailed group information
- Monitor group members and contributions
- Track group performance metrics
- Export group data to CSV

### 💰 Withdrawal Management (APPROVAL SYSTEM)
- View all pending withdrawal requests
- Review withdrawal details and amounts
- **Approve withdrawals** for processing
- **Reject withdrawals** with reasons
- Monitor bank account information
- Real-time notifications
- Track withdrawal history
- Fraud detection integration

### 📊 Analytics & Reports (COMPREHENSIVE)
- User growth analytics with trends
- Group performance metrics
- Transaction analytics and trends
- Revenue analytics
- **Export reports to CSV/JSON**
- Date range filtering
- Custom period selection (today, week, month, year)
- Success rate tracking

### 💳 Contributions Tracking
- View all contributions
- Monitor payment status
- Track success rates
- Filter by group or user

### 💸 Transaction Management
- Complete transaction history
- Advanced filtering options
- Export capabilities
- Fraud detection alerts

### ⚙️ System Settings
- Platform configuration
- Admin user management
- System health monitoring
- Audit logs
- Security settings
- Date range filtering
- Custom period selection

### ⚙️ System Settings
- Platform configuration
- Admin user management
- System health monitoring
- Audit logs
- Security settings

## Quick Start

### 1. Open Dashboard
```powershell
.\open-admin-dashboard.ps1
```

Or open `admin-dashboard/index.html` in your browser.

### 2. Login
- **Email**: `admin@ajo.test`
- **Password**: `password`

### 3. Start Managing
Navigate tto access different sections.

## Dashboard Sections

### Dashboard
- Overview of all platform metrics
- Quick stats cards
- System health indicators
- Pending actions summary

### Users
- Complete user list with pagination
- Advanced search and filtering
- User profile details
- Account management actions
- Bulk operations support

### KYC Approvals
- Pending submissions queue
- Document review interface
- Approval/rejection workflow
- Reason tracking

### Groups
- All groups overview

- Group details and members
- Performance tracking

### Contributions
- Contribution history
- Payment tracking
- Success rate monitoring

### Withdrawals
- Pending requests queue
- Approval workflow
- Bank account verification
- Transaction tracking

### Transactions
- Complete transaction history
- Advanced filtering
- Export capabilities

### Analytics
- User analytics
- Group analytics
- Transaction analytics
- Revenue reports
- Custom date ranges
- Export to CSV/JSON

### Settings
- System configuration
- Admin management
- Security settings
- Audit logs

## Key Features

### 🔍 Advanced Search
- Global search across all sections
- Section-specific search
- Real-time filtering

### 📱 Responsive Design
- Works on desktop, tablet, and mobile
- Collapsible sidebar
- Touch-friendly interface
Check API documentation

## License

Same as the main Ajo Platform project.
`styles.css`
3. Add functionality in `app.js`
4. Update navigation in sidebar

## Future Enhancements

- [ ] Real-time updates with WebSockets
- [ ] Advanced charts and graphs
- [ ] Bulk user operations
- [ ] Email notifications
- [ ] Two-factor authentication
- [ ] Activity logs viewer
- [ ] System backup/restore
- [ ] Multi-language support
- [ ] Dark mode
- [ ] Mobile app

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review browser console for errors
3. Verify backend logs
4.  ```

2. Check API URL in `app.js`

3. Verify CORS is enabled in backend

### Data Not Loading
1. Check browser console for errors
2. Verify authentication token
3. Check API endpoints are accessible
4. Verify admin role in database

## Development

### File Structure
```
admin-dashboard/
├── index.html      # Main HTML structure
├── styles.css      # All styling
├── app.js          # JavaScript functionality
└── README.md       # This file
```

### Adding New Features
1. Add section in `index.html`
2. Add styles in sion management
- Secure API calls
- Role-based access control
- Audit trail logging

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Opera (latest)

## Troubleshooting

### Can't Login
1. Verify admin user exists:
   ```powershell
   docker exec ajo_laravel php artisan db:seed --class=AdminUserSeeder
   ```

2. Check credentials:
   - Email: `admin@ajo.test`
   - Password: `password`

### Connection Error
1. Verify backend is running:
   ```powershell
   docker-compose ps
  cs/groups` - Group analytics
- `GET /admin/analytics/transactions` - Transaction analytics
- `GET /admin/analytics/revenue` - Revenue analytics

## Configuration

### API Base URL
Update in `app.js`:
```javascript
const API_BASE_URL = 'http://localhost:8002/api/v1';
```

### Styling
Customize colors in `styles.css`:
```css
:root {
    --primary: #3498db;
    --success: #27ae60;
    --danger: #e74c3c;
    --warning: #f39c12;
    /* ... */
}
```

## Security Features

- Token-based authentication
- Automatic sesser

### KYC
- `GET /admin/kyc/pending` - Pending KYC
- `POST /admin/kyc/{id}/approve` - Approve KYC
- `POST /admin/kyc/{id}/reject` - Reject KYC

### Groups
- `GET /admin/groups` - List groups
- `GET /admin/groups/{id}` - Group details

### Withdrawals
- `GET /admin/withdrawals/pending` - Pending withdrawals
- `POST /admin/withdrawals/{id}/approve` - Approve withdrawal
- `POST /admin/withdrawals/{id}/reject` - Reject withdrawal

### Analytics
- `GET /admin/analytics/users` - User analytics
- `GET /admin/analytidate range exports

### 🎨 Modern UI
- Clean, professional design
- Intuitive navigation
- Color-coded status indicators
- Icon-based actions

### ⚡ Performance
- Fast loading times
- Efficient data fetching
- Pagination support
- Lazy loading

## API Endpoints Used

### Dashboard
- `GET /admin/dashboard/stats` - Dashboard statistics

### Users
- `GET /admin/users` - List users
- `GET /admin/users/{id}` - User details
- `PUT /admin/users/{id}/suspend` - Suspend user
- `PUT /admin/users/{id}/activate` - Activate u
### 🔔 Real-Time Notifications
- Badge notifications for pending actions
- Success/error messages
- System alerts

### 📥 Export Capabilities
- Export users to CSV
- Export groups to CSV
- Export analytics reports
- Custom 