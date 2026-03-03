# Niche Social Network

A full-stack social networking platform with real-time private messaging, built with PHP, MySQL, Redis, and WebSockets.

## Features

### Core Functionality
- **User Authentication** - JWT-based auth with email verification
- **User Profiles** - Customizable profiles with avatar, cover photo, bio
- **Social Feed** - Personalized feed from followed users
- **Posts & Comments** - Create posts with images, nested comments (1 level)
- **Likes** - Like posts with real-time counters
- **Follow System** - Follow/unfollow users, view followers/following
- **Real-Time Messaging** - WebSocket-based private 1:1 messaging
- **Notifications** - In-app notifications for likes, comments, follows, messages
- **File Uploads** - Image and video uploads with automatic processing
- **Content Moderation** - Report system with admin dashboard
- **Analytics** - Track user activity and platform statistics

### Technical Features
- **PSR-12 Coding Standards** - Clean, maintainable PHP code
- **Docker Environment** - Complete local development setup
- **Rate Limiting** - Redis-based request throttling
- **Image Processing** - Automatic resizing and thumbnail generation
- **WebSocket Server** - Ratchet-based real-time communication
- **Background Jobs** - Queue system for async tasks
- **Pusher Integration** - Alternative to self-hosted WebSockets
- **Horizontal Scaling** - Redis pub/sub for multi-server deployments

## Technology Stack

- **Backend:** PHP 8.2 with PSR-4 autoloading
- **Database:** MySQL 8.0
- **Cache:** Redis 7
- **WebSocket:** Ratchet (PHP)
- **Web Server:** Nginx + PHP-FPM
- **Containerization:** Docker & Docker Compose
- **Frontend:** Vanilla JavaScript (demo pages)

## Project Structure

```
├── docker/
│   ├── Dockerfile              # PHP-FPM container
│   └── nginx.conf              # Nginx configuration
├── docker-compose.yml          # Docker orchestration
├── composer.json               # PHP dependencies
├── .env.example                # Environment variables template
├── database/
│   ├── migrations/             # SQL schema migrations
│   │   ├── 001_users.sql
│   │   ├── 002_posts.sql
│   │   ├── 003_comments.sql
│   │   ├── 004_likes.sql
│   │   ├── 005_follows.sql
│   │   ├── 006_messages.sql
│   │   ├── 007_notifications.sql
│   │   ├── 008_events.sql
│   │   └── 009_reports.sql
│   ├── migrate.php             # Migration runner
│   └── seed.php                # Test data seeder
├── src/
│   ├── Config.php              # Configuration manager
│   ├── Database.php            # Database connection
│   ├── RedisClient.php         # Redis connection
│   ├── Models/                 # Data models
│   │   ├── BaseModel.php
│   │   ├── User.php
│   │   ├── Post.php
│   │   ├── Comment.php
│   │   ├── Message.php
│   │   ├── Notification.php
│   │   ├── Event.php
│   │   └── Report.php
│   ├── Services/               # Business logic
│   │   ├── AuthService.php
│   │   ├── FileUploadService.php
│   │   ├── NotificationService.php
│   │   ├── EmailService.php
│   │   └── QueueService.php
│   ├── Middleware/             # Request middleware
│   │   ├── AuthMiddleware.php
│   │   └── RateLimitMiddleware.php
│   └── Controllers/            # API controllers
│       ├── AuthController.php
│       ├── UserController.php
│       ├── PostController.php
│       ├── CommentController.php
│       ├── MessageController.php
│       ├── NotificationController.php
│       ├── UploadController.php
│       └── AdminController.php
├── workers/
│   ├── websocket-server.php    # WebSocket server (Ratchet)
│   └── queue-worker.php        # Background job processor
├── public/
│   ├── index.php               # API entry point
│   ├── style.css               # Frontend styles
│   ├── login.html              # Auth demo
│   ├── feed.html               # Feed demo
│   ├── messaging.html          # Messaging demo
│   └── admin.html              # Admin dashboard demo
├── API.md                      # API documentation
└── README.md                   # This file
```

## Prerequisites

- **Docker** (20.10+)
- **Docker Compose** (2.0+)
- **Git**

That's it! Everything else runs in containers.

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd social-network
```

### 2. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` and configure your settings (MySQL password, JWT secret, etc.):

```bash
# Database
DB_HOST=mysql
DB_PORT=3306
DB_NAME=socialnet
DB_USER=root
DB_PASS=your_secure_password_here

# JWT
JWT_SECRET=your_random_secret_key_here
JWT_EXPIRES_IN=86400

# WebSocket
WS_HOST=0.0.0.0
WS_PORT=8081

# Optional: Pusher (for production)
PUSHER_ENABLED=false
PUSHER_APP_ID=
PUSHER_KEY=
PUSHER_SECRET=
PUSHER_CLUSTER=us2
```

**Important:** Change `DB_PASS` and `JWT_SECRET` to secure random values!

### 3. Start Docker Containers

```bash
docker-compose up -d
```

This starts 6 services:
- `php` - PHP-FPM 8.2
- `nginx` - Web server (port 8000)
- `mysql` - Database (port 3306)
- `redis` - Cache (port 6379)
- `websocket` - WebSocket server (port 8081)
- `queue_worker` - Background job processor

### 4. Install Dependencies

```bash
docker-compose exec php composer install
```

### 5. Run Database Migrations

```bash
docker-compose exec php php database/migrate.php
```

This creates 9 tables:
- `users` - User accounts and profiles
- `posts` - User posts
- `comments` - Post comments
- `likes` - Post likes
- `follows` - Follow relationships
- `messages` - Private messages
- `notifications` - In-app notifications
- `events` - Analytics events
- `reports` - Content moderation reports

### 6. Seed Test Data (Optional)

```bash
docker-compose exec php php database/seed.php
```

This creates:
- 20 test users
- 50 posts
- 150+ comments
- Follow relationships
- Messages
- Notifications

**Test Credentials:**
- Email: `alicesmith@example.com` (Admin)
- Password: `password123`

All 20 users have the same password: `password123`

## Running the Application

### Access the Application

**Frontend Demo Pages:**
- Login: http://localhost:8000/login.html
- Feed: http://localhost:8000/feed.html
- Messaging: http://localhost:8000/messaging.html
- Admin: http://localhost:8000/admin.html

**API Base URL:** http://localhost:8000

**WebSocket URL:** ws://localhost:8081

### Verify Services

Check all containers are running:

```bash
docker-compose ps
```

Expected output:
```
NAME                  SERVICE       STATUS
social-network-nginx       nginx         running
social-network-php         php           running
social-network-mysql       mysql         running
social-network-redis       redis         running
social-network-websocket   websocket     running
social-network-queue       queue_worker  running
```

### View Logs

**All services:**
```bash
docker-compose logs -f
```

**Specific service:**
```bash
docker-compose logs -f websocket
docker-compose logs -f php
docker-compose logs -f nginx
```

### Stop the Application

```bash
docker-compose down
```

To remove volumes (deletes database data):
```bash
docker-compose down -v
```

## Development Workflow

### Making Code Changes

1. Edit files in `src/`, `public/`, or `workers/`
2. Changes are immediately reflected (volumes are mounted)
3. For PHP changes, no restart needed
4. For WebSocket server changes, restart the service:

```bash
docker-compose restart websocket
```

### Database Changes

**Add a new migration:**

1. Create `database/migrations/010_your_migration.sql`
2. Run migrations:
```bash
docker-compose exec php php database/migrate.php
```

**Reset database:**
```bash
docker-compose exec mysql mysql -uroot -pyour_password -e "DROP DATABASE socialnet; CREATE DATABASE socialnet;"
docker-compose exec php php database/migrate.php
docker-compose exec php php database/seed.php
```

### Testing API Endpoints

**Using cURL:**

```bash
# Login
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alicesmith@example.com","password":"password123"}'

# Get feed (replace TOKEN)
curl http://localhost:8000/posts/feed \
  -H "Authorization: Bearer TOKEN"

# Create post
curl -X POST http://localhost:8000/posts \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Hello world!","visibility":"public"}'
```

**Using the demo frontend:**

1. Open http://localhost:8000/login.html
2. Login with test credentials
3. Navigate through the demo pages

See [API.md](API.md) for complete API documentation.

### Code Quality

**Check PSR-12 compliance:**

```bash
docker-compose exec php vendor/bin/phpcs --standard=PSR12 src/
```

**Auto-fix issues:**

```bash
docker-compose exec php vendor/bin/phpcbf --standard=PSR12 src/
```

## WebSocket Server

The WebSocket server runs automatically via Docker Compose.

### Manual Control

**Restart WebSocket server:**
```bash
docker-compose restart websocket
```

**View WebSocket logs:**
```bash
docker-compose logs -f websocket
```

### Testing WebSocket Connection

Using browser console:

```javascript
const token = 'YOUR_JWT_TOKEN';
const ws = new WebSocket(`ws://localhost:8081?token=${token}`);

ws.onopen = () => {
  console.log('Connected');
  ws.send(JSON.stringify({
    type: 'message',
    recipient_id: 2,
    content: 'Hello!'
  }));
};

ws.onmessage = (event) => {
  console.log('Received:', JSON.parse(event.data));
};
```

## Production Deployment

### Option 1: Self-Hosted WebSocket (Ratchet)

1. **Update environment:**
   - Set proper database host/credentials
   - Generate strong JWT secret
   - Configure mail server for emails

2. **HTTPS/WSS Setup:**
   - Use reverse proxy (Nginx/Apache) with SSL
   - Proxy WebSocket connections through SSL
   - Update `WS_HOST` to your domain

3. **Process Management:**
   - Use Supervisor to keep WebSocket server running
   - Set up log rotation
   - Monitor with tools like New Relic

4. **Scaling:**
   - Use Redis pub/sub (already implemented)
   - Run multiple WebSocket servers
   - Load balance with Nginx

### Option 2: Pusher (Managed Service)

1. **Sign up at Pusher.com**

2. **Update `.env`:**
```bash
PUSHER_ENABLED=true
PUSHER_APP_ID=your_app_id
PUSHER_KEY=your_key
PUSHER_SECRET=your_secret
PUSHER_CLUSTER=us2
```

3. **Update frontend:**
```html
<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script>
const pusher = new Pusher('YOUR_KEY', {
  cluster: 'us2'
});

const channel = pusher.subscribe(`user.${userId}`);
channel.bind('message', (data) => {
  console.log('New message:', data);
});
</script>
```

4. **Disable WebSocket container** in `docker-compose.yml`

## Configuration Options

### .env Variables

See `.env.example` for all available options:

**Core:**
- `APP_ENV` - Environment (development/production)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_URL` - Application URL

**Database:**
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

**Redis:**
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`

**Authentication:**
- `JWT_SECRET` - Secret key for JWT signing
- `JWT_EXPIRES_IN` - Token lifetime (seconds)
- `PASSWORD_ALGO` - argon2id or bcrypt

**WebSocket:**
- `WS_HOST`, `WS_PORT`
- `WS_REDIS_CHANNEL` - Redis pub/sub channel

**Email:**
- `MAIL_*` - SMTP configuration

**Uploads:**
- `UPLOAD_*` - File upload limits and paths

**Rate Limiting:**
- `RATE_LIMIT_*` - Request throttling

**Pusher:**
- `PUSHER_*` - Pusher.com configuration

## Troubleshooting

### Containers won't start

```bash
# Check logs
docker-compose logs

# Rebuild containers
docker-compose down
docker-compose up -d --build
```

### Database connection failed

1. Check MySQL is running: `docker-compose ps mysql`
2. Verify credentials in `.env`
3. Test connection:
```bash
docker-compose exec mysql mysql -uroot -p$DB_PASS
```

### WebSocket connection fails

1. Check WebSocket server is running: `docker-compose logs websocket`
2. Verify port 8081 is accessible
3. Check firewall rules
4. Test connection: `telnet localhost 8081`

### File upload errors

1. Check permissions:
```bash
docker-compose exec php chmod -R 777 public/uploads
```

2. Verify upload limits in `.env`
3. Check Nginx upload size in `docker/nginx.conf`

### Rate limit errors

Clear Redis cache:
```bash
docker-compose exec redis redis-cli FLUSHDB
```

## Performance Optimization

### Database

1. **Add indexes** for frequently queried columns
2. **Enable query caching** in MySQL
3. **Use connection pooling**
4. **Optimize slow queries** with EXPLAIN

### Redis

1. **Enable persistence** for production
2. **Configure maxmemory** policy
3. **Use pipelining** for bulk operations

### PHP

1. **Enable OPcache** (already configured in Dockerfile)
2. **Increase memory_limit** for image processing
3. **Use CDN** for uploaded files
4. **Enable response compression**

### WebSocket

1. **Increase max connections** in Ratchet
2. **Use Redis pub/sub** for multi-server setup
3. **Implement heartbeat** to detect dead connections
4. **Monitor memory usage**

## Security Considerations

1. **Change default passwords** in `.env`
2. **Use strong JWT secret** (32+ random characters)
3. **Enable HTTPS** in production
4. **Validate all user input** (already implemented)
5. **Set secure headers** in Nginx
6. **Regular security updates** for dependencies
7. **Rate limit sensitive endpoints** (already implemented)
8. **Sanitize file uploads** (already implemented)
9. **Use prepared statements** (already implemented)
10. **Implement CORS** properly for production

## Testing

See [TESTING.md](TESTING.md) for comprehensive test plan.

**Quick Tests:**

1. **Authentication:**
   - Signup → Verify email → Login → Receive token

2. **Social Features:**
   - Create post → View in feed → Like → Comment

3. **Messaging:**
   - Send message via API → Receive via WebSocket

4. **Admin:**
   - Report content → Review as admin → Ban user

## API Documentation

Complete API reference available in [API.md](API.md).

**Quick Reference:**

- `POST /auth/login` - Authenticate
- `GET /posts/feed` - Get feed
- `POST /posts` - Create post
- `POST /messages` - Send message
- `GET /notifications` - Get notifications

WebSocket: `ws://localhost:8081?token=JWT_TOKEN`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow PSR-12 coding standards
4. Write tests for new features
5. Submit a pull request

## License

This project is proprietary software. All rights reserved.

## Support

For issues or questions:
- Check [Troubleshooting](#troubleshooting) section
- Review [API.md](API.md) for API questions
- Check Docker logs: `docker-compose logs`

## Roadmap

Potential future enhancements:

- [ ] Groups/Communities
- [ ] Story/Status updates
- [ ] Video calls (WebRTC)
- [ ] Mobile apps (React Native)
- [ ] GraphQL API
- [ ] Elasticsearch for search
- [ ] Redis Cluster for HA
- [ ] Kubernetes deployment
- [ ] End-to-end encryption
- [ ] Multi-language support

---

**Built with ❤️ using PHP, MySQL, Redis, and WebSockets**
