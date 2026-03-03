# Social Network API Documentation

## Overview

This is a RESTful API for a niche social network with real-time private messaging capabilities. The API uses JSON for request/response bodies and JWT for authentication.

**Base URL:** `http://localhost:8000`

**WebSocket URL:** `ws://localhost:8081`

## Authentication

Most endpoints require authentication via JWT token passed in the `Authorization` header:

```
Authorization: Bearer <jwt_token>
```

Tokens are obtained from the `/auth/login` endpoint and are valid for 24 hours.

## Rate Limiting

All endpoints are rate-limited using Redis:
- Default: 60 requests per 60 seconds
- Authentication endpoints have stricter limits (see individual endpoints)
- Rate limits return HTTP 429 when exceeded

## Common Response Codes

- `200 OK` - Request succeeded
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

---

## Authentication Endpoints

### POST /auth/signup

Create a new user account.

**Rate Limit:** 5 requests per 5 minutes

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "SecurePassword123!",
  "full_name": "John Doe",
  "bio": "Software developer and coffee enthusiast"
}
```

**Response 201:**
```json
{
  "message": "Signup successful. Please check your email to verify your account.",
  "user_id": 42
}
```

**Errors:**
- `400` - Email already exists
- `400` - Password too weak (min 8 characters)

---

### POST /auth/login

Authenticate and receive JWT token.

**Rate Limit:** 10 requests per 1 minute

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "SecurePassword123!"
}
```

**Response 200:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 42,
    "email": "user@example.com",
    "full_name": "John Doe",
    "role": "user",
    "is_verified": true,
    "avatar_url": "https://example.com/avatars/user42.jpg"
  }
}
```

**Errors:**
- `401` - Invalid credentials
- `403` - Account not verified
- `403` - Account banned

---

### POST /auth/verify-email

Verify email address with verification token.

**Request Body:**
```json
{
  "token": "abc123def456..."
}
```

**Response 200:**
```json
{
  "message": "Email verified successfully"
}
```

---

### POST /auth/resend-verification

Resend verification email.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response 200:**
```json
{
  "message": "Verification email sent"
}
```

---

## User Endpoints

### GET /user/me

Get current authenticated user's profile.

**Auth Required:** Yes

**Response 200:**
```json
{
  "id": 42,
  "email": "user@example.com",
  "full_name": "John Doe",
  "bio": "Software developer and coffee enthusiast",
  "avatar_url": "https://example.com/avatars/user42.jpg",
  "cover_url": "https://example.com/covers/user42.jpg",
  "role": "user",
  "is_verified": true,
  "is_banned": false,
  "created_at": "2024-01-15T10:30:00Z",
  "stats": {
    "posts_count": 127,
    "followers_count": 453,
    "following_count": 289
  }
}
```

---

### GET /user/:id

Get user profile by ID.

**Auth Required:** Yes

**Response 200:**
```json
{
  "id": 42,
  "full_name": "John Doe",
  "bio": "Software developer and coffee enthusiast",
  "avatar_url": "https://example.com/avatars/user42.jpg",
  "cover_url": "https://example.com/covers/user42.jpg",
  "created_at": "2024-01-15T10:30:00Z",
  "stats": {
    "posts_count": 127,
    "followers_count": 453,
    "following_count": 289
  },
  "is_following": true
}
```

---

### PUT /user/me

Update current user's profile.

**Auth Required:** Yes

**Request Body:**
```json
{
  "full_name": "Jane Doe",
  "bio": "Updated bio text",
  "location": "San Francisco, CA",
  "website": "https://janedoe.com"
}
```

**Response 200:**
```json
{
  "message": "Profile updated successfully"
}
```

---

### GET /user/:id/posts

Get posts by specific user.

**Auth Required:** Yes

**Query Parameters:**
- `limit` (optional, default: 20) - Number of posts to return
- `offset` (optional, default: 0) - Pagination offset

**Response 200:**
```json
[
  {
    "id": 123,
    "user_id": 42,
    "user_name": "John Doe",
    "user_avatar": "https://example.com/avatars/user42.jpg",
    "content": "Just deployed my new app!",
    "image_url": "https://example.com/posts/123.jpg",
    "visibility": "public",
    "created_at": "2024-03-20T14:30:00Z",
    "likes_count": 45,
    "comments_count": 12,
    "is_liked": true
  }
]
```

---

### GET /user/:id/followers

Get list of user's followers.

**Auth Required:** Yes

**Response 200:**
```json
[
  {
    "id": 15,
    "full_name": "Alice Smith",
    "avatar_url": "https://example.com/avatars/user15.jpg",
    "bio": "Designer & photographer",
    "followed_at": "2024-02-10T09:15:00Z"
  }
]
```

---

### GET /user/:id/following

Get list of users being followed.

**Auth Required:** Yes

**Response 200:**
```json
[
  {
    "id": 28,
    "full_name": "Bob Johnson",
    "avatar_url": "https://example.com/avatars/user28.jpg",
    "bio": "Tech enthusiast",
    "followed_at": "2024-01-25T11:45:00Z"
  }
]
```

---

### POST /user/:id/follow

Follow a user.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "User followed successfully"
}
```

**Errors:**
- `400` - Cannot follow yourself
- `400` - Already following this user

---

### DELETE /user/:id/unfollow

Unfollow a user.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "User unfollowed successfully"
}
```

---

## Post Endpoints

### GET /posts/feed

Get personalized feed of posts from followed users.

**Auth Required:** Yes

**Query Parameters:**
- `limit` (optional, default: 20)
- `offset` (optional, default: 0)

**Response 200:**
```json
[
  {
    "id": 456,
    "user_id": 28,
    "user_name": "Bob Johnson",
    "user_avatar": "https://example.com/avatars/user28.jpg",
    "content": "Great day for coding!",
    "image_url": null,
    "visibility": "public",
    "created_at": "2024-03-21T10:00:00Z",
    "likes_count": 23,
    "comments_count": 5,
    "is_liked": false
  }
]
```

---

### POST /posts

Create a new post.

**Auth Required:** Yes

**Rate Limit:** 20 requests per hour

**Request Body:**
```json
{
  "content": "Just launched my new website! Check it out.",
  "image_url": "https://example.com/uploads/image123.jpg",
  "visibility": "public"
}
```

**Visibility Options:** `public`, `followers`, `private`

**Response 201:**
```json
{
  "message": "Post created successfully",
  "post_id": 789
}
```

---

### POST /posts/:id/like

Like a post.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Post liked successfully"
}
```

---

### DELETE /posts/:id/unlike

Unlike a post.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Post unliked successfully"
}
```

---

### DELETE /posts/:id

Delete a post (owner or admin only).

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Post deleted successfully"
}
```

**Errors:**
- `403` - Not authorized to delete this post

---

## Comment Endpoints

### GET /comments/:postId

Get comments for a specific post.

**Auth Required:** Yes

**Response 200:**
```json
[
  {
    "id": 234,
    "user_id": 15,
    "user_name": "Alice Smith",
    "user_avatar": "https://example.com/avatars/user15.jpg",
    "content": "Amazing work!",
    "created_at": "2024-03-21T11:30:00Z",
    "replies": [
      {
        "id": 235,
        "user_id": 42,
        "user_name": "John Doe",
        "user_avatar": "https://example.com/avatars/user42.jpg",
        "content": "Thanks!",
        "created_at": "2024-03-21T11:45:00Z"
      }
    ]
  }
]
```

---

### POST /comments

Create a comment.

**Auth Required:** Yes

**Request Body:**
```json
{
  "post_id": 456,
  "content": "Great post!",
  "parent_id": null
}
```

Note: `parent_id` is used for replies (max 1 level of nesting).

**Response 201:**
```json
{
  "message": "Comment created successfully",
  "comment_id": 567
}
```

---

### DELETE /comments/:id

Delete a comment (owner or admin only).

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Comment deleted successfully"
}
```

---

## Messaging Endpoints

### GET /messages/conversations

Get list of conversations with latest message.

**Auth Required:** Yes

**Response 200:**
```json
[
  {
    "user_id": 28,
    "user_name": "Bob Johnson",
    "user_avatar": "https://example.com/avatars/user28.jpg",
    "last_message": "See you tomorrow!",
    "last_message_time": "2024-03-21T16:30:00Z",
    "unread_count": 2
  }
]
```

---

### GET /messages/:userId

Get conversation with specific user.

**Auth Required:** Yes

**Query Parameters:**
- `limit` (optional, default: 50)
- `offset` (optional, default: 0)

**Response 200:**
```json
[
  {
    "id": 789,
    "sender_id": 42,
    "recipient_id": 28,
    "content": "Hey, how are you?",
    "is_delivered": true,
    "is_read": true,
    "created_at": "2024-03-21T15:00:00Z"
  },
  {
    "id": 790,
    "sender_id": 28,
    "recipient_id": 42,
    "content": "I'm good, thanks!",
    "is_delivered": true,
    "is_read": true,
    "created_at": "2024-03-21T15:05:00Z"
  }
]
```

---

### POST /messages

Send a message.

**Auth Required:** Yes

**Request Body:**
```json
{
  "recipient_id": 28,
  "content": "Hello! How's your project going?"
}
```

**Response 201:**
```json
{
  "message": "Message sent successfully",
  "message_id": 891
}
```

---

### PUT /messages/:id/read

Mark message as read.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Message marked as read"
}
```

---

### PUT /messages/read/:userId

Mark all messages from user as read.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Messages marked as read"
}
```

---

### GET /messages/unread/count

Get total unread message count.

**Auth Required:** Yes

**Response 200:**
```json
{
  "count": 5
}
```

---

## Notification Endpoints

### GET /notifications

Get user notifications.

**Auth Required:** Yes

**Query Parameters:**
- `limit` (optional, default: 20)
- `offset` (optional, default: 0)
- `unread_only` (optional, default: false)

**Response 200:**
```json
[
  {
    "id": 123,
    "type": "like",
    "message": "Alice Smith liked your post",
    "data": {
      "user_id": 15,
      "post_id": 456
    },
    "is_read": false,
    "created_at": "2024-03-21T17:00:00Z"
  },
  {
    "id": 124,
    "type": "comment",
    "message": "Bob Johnson commented on your post",
    "data": {
      "user_id": 28,
      "post_id": 456,
      "comment_id": 234
    },
    "is_read": false,
    "created_at": "2024-03-21T17:15:00Z"
  }
]
```

**Notification Types:** `like`, `comment`, `follow`, `message`

---

### GET /notifications/count

Get unread notification count.

**Auth Required:** Yes

**Response 200:**
```json
{
  "count": 12
}
```

---

### PUT /notifications/:id/read

Mark notification as read.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "Notification marked as read"
}
```

---

### PUT /notifications/read/all

Mark all notifications as read.

**Auth Required:** Yes

**Response 200:**
```json
{
  "message": "All notifications marked as read"
}
```

---

## Upload Endpoints

### POST /upload/image

Upload an image file.

**Auth Required:** Yes

**Content-Type:** `multipart/form-data`

**Form Fields:**
- `image` (file) - Image file (JPG, PNG, GIF, max 10MB)

**Response 200:**
```json
{
  "url": "https://example.com/uploads/abc123.jpg",
  "thumbnail_url": "https://example.com/uploads/thumbs/abc123.jpg"
}
```

**Errors:**
- `400` - No file uploaded
- `400` - File too large (max 10MB)
- `400` - Invalid file type

---

### POST /upload/video

Upload a video file.

**Auth Required:** Yes

**Content-Type:** `multipart/form-data`

**Form Fields:**
- `video` (file) - Video file (MP4, MOV, AVI, max 100MB)

**Response 200:**
```json
{
  "url": "https://example.com/uploads/video123.mp4"
}
```

---

### POST /upload/avatar

Upload profile avatar (automatically updates user profile).

**Auth Required:** Yes

**Content-Type:** `multipart/form-data`

**Form Fields:**
- `avatar` (file) - Image file

**Response 200:**
```json
{
  "message": "Avatar updated successfully",
  "avatar_url": "https://example.com/avatars/user42.jpg"
}
```

---

### POST /upload/cover

Upload profile cover image.

**Auth Required:** Yes

**Content-Type:** `multipart/form-data`

**Form Fields:**
- `cover` (file) - Image file

** Response 200:**
```json
{
  "message": "Cover photo updated successfully",
  "cover_url": "https://example.com/covers/user42.jpg"
}
```

---

## Admin Endpoints

All admin endpoints require `role: admin`.

### GET /admin/reports

Get content reports.

**Auth Required:** Yes (Admin)

**Query Parameters:**
- `status` (optional) - Filter by status: `pending`, `reviewed`, `resolved`, `dismissed`
- `content_type` (optional) - Filter by type: `user`, `post`, `comment`

**Response 200:**
```json
[
  {
    "id": 45,
    "reporter_id": 42,
    "reporter_name": "John Doe",
    "reported_id": 99,
    "reported_name": "Spammer User",
    "content_type": "post",
    "content_id": 888,
    "content_preview": "Spam content here...",
    "reason": "spam",
    "status": "pending",
    "created_at": "2024-03-21T18:00:00Z"
  }
]
```

---

### PUT /admin/reports/:id

Update report status.

**Auth Required:** Yes (Admin)

**Request Body:**
```json
{
  "status": "resolved"
}
```

**Response 200:**
```json
{
  "message": "Report updated successfully"
}
```

---

### POST /admin/users/:id/ban

Ban a user.

**Auth Required:** Yes (Admin)

**Request Body:**
```json
{
  "reason": "Repeated policy violations"
}
```

**Response 200:**
```json
{
  "message": "User banned successfully"
}
```

---

### POST /admin/users/:id/unban

Unban a user.

**Auth Required:** Yes (Admin)

**Response 200:**
```json
{
  "message": "User unbanned successfully"
}
```

---

### DELETE /admin/posts/:id

Delete any post (admin override).

**Auth Required:** Yes (Admin)

**Response 200:**
```json
{
  "message": "Post deleted successfully"
}
```

---

### DELETE /admin/comments/:id

Delete any comment (admin override).

**Auth Required:** Yes (Admin)

**Response 200:**
```json
{
  "message": "Comment deleted successfully"
}
```

---

### GET /admin/stats

Get platform statistics.

**Auth Required:** Yes (Admin)

**Response 200:**
```json
{
  "total_users": 1247,
  "total_posts": 8934,
  "total_comments": 23456,
  "total_messages": 45678,
  "pending_reports": 12,
  "banned_users": 5
}
```

---

### GET /admin/analytics

Get analytics data.

**Auth Required:** Yes (Admin)

**Query Parameters:**
- `days` (optional, default: 30) - Number of days to retrieve

**Response 200:**
```json
{
  "daily_stats": [
    {
      "date": "2024-03-21",
      "login": 450,
      "post_created": 123,
      "comment_created": 345,
      "message_sent": 678
    }
  ],
  "top_users": [
    {
      "user_id": 42,
      "user_name": "John Doe",
      "event_count": 234
    }
  ]
}
```

---

## WebSocket Protocol

### Connection

Connect to WebSocket server with JWT token:

```
ws://localhost:8081?token=<jwt_token>
```

### Message Format

All messages are JSON objects with a `type` field:

#### Client → Server Messages

**Send Message:**
```json
{
  "type": "message",
  "recipient_id": 28,
  "content": "Hello via WebSocket!"
}
```

**Typing Indicator:**
```json
{
  "type": "typing",
  "recipient_id": 28
}
```

**Ping:**
```json
{
  "type": "ping"
}
```

#### Server → Client Messages

**Incoming Message:**
```json
{
  "type": "message",
  "id": 892,
  "sender_id": 28,
  "sender_name": "Bob Johnson",
  "content": "Hey there!",
  "timestamp": "2024-03-21T19:00:00Z"
}
```

**Message Sent Confirmation:**
```json
{
  "type": "message_sent",
  "message_id": 893,
  "timestamp": "2024-03-21T19:00:15Z"
}
```

**Typing Indicator:**
```json
{
  "type": "typing",
  "user_id": 28,
  "user_name": "Bob Johnson"
}
```

**Notification:**
```json
{
  "type": "notification",
  "notification_type": "like",
  "message": "Alice Smith liked your post",
  "data": {
    "user_id": 15,
    "post_id": 456
  }
}
```

**Pong:**
```json
{
  "type": "pong"
}
```

**Error:**
```json
{
  "type": "error",
  "message": "Authentication failed"
}
```

### Connection Lifecycle

1. Client connects with JWT token in query string
2. Server authenticates and stores connection
3. Client can send/receive messages in real-time
4. Server pushes notifications via Redis pub/sub
5. Client disconnects, server cleans up connection mapping

---

## Error Responses

All error responses follow this format:

```json
{
  "error": "Error message describing what went wrong"
}
```

Common error scenarios:

**401 Unauthorized:**
```json
{
  "error": "Authentication required"
}
```

**403 Forbidden:**
```json
{
  "error": "Insufficient permissions"
}
```

**404 Not Found:**
```json
{
  "error": "Resource not found"
}
```

**429 Too Many Requests:**
```json
{
  "error": "Rate limit exceeded. Please try again later."
}
```

**500 Internal Server Error:**
```json
{
  "error": "Internal server error"
}
```

---

## Pusher Alternative

For production deployments, you can use Pusher instead of the Ratchet WebSocket server.

Configure in `.env`:
```
PUSHER_ENABLED=true
PUSHER_APP_ID=your_app_id
PUSHER_KEY=your_key
PUSHER_SECRET=your_secret
PUSHER_CLUSTER=us2
```

Client-side integration:
```javascript
const pusher = new Pusher('your_key', {
  cluster: 'us2'
});

const channel = pusher.subscribe(`user.${userId}`);
channel.bind('message', (data) => {
  console.log('New message:', data);
});
```

---

## Best Practices

1. **Always include the Authorization header** for protected endpoints
2. **Store JWT tokens securely** (localStorage or httpOnly cookies)
3. **Handle rate limits gracefully** with exponential backoff
4. **Validate user input** on the client side before sending requests
5. **Use WebSocket for real-time features** (messaging, notifications)
6. **Implement reconnection logic** for WebSocket connections
7. **Cache frequently accessed data** on the client side
8. **Handle errors consistently** across your application
9. **Test with realistic data volumes** to ensure performance

---

## Testing Examples

### cURL Examples

**Login:**
```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

**Get Feed (with auth):**
```bash
curl http://localhost:8000/posts/feed \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**Create Post:**
```bash
curl -X POST http://localhost:8000/posts \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{"content":"My first post!","visibility":"public"}'
```

**Upload Image:**
```bash
curl -X POST http://localhost:8000/upload/image \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -F "image=@/path/to/image.jpg"
```

### JavaScript Examples

See the frontend HTML files (`login.html`, `feed.html`, `messaging.html`, `admin.html`) for complete working examples of API integration.

---

## WebSocket Testing

Using JavaScript in browser console:

```javascript
const ws = new WebSocket('ws://localhost:8081?token=YOUR_JWT_TOKEN');

ws.onopen = () => {
  console.log('Connected');
  ws.send(JSON.stringify({
    type: 'message',
    recipient_id: 28,
    content: 'Test message'
  }));
};

ws.onmessage = (event) => {
  console.log('Received:', JSON.parse(event.data));
};
```

---

## Versioning

Current API version: **1.0**

The API currently does not use versioning in the URL. Future versions may introduce `/v2/` prefix for breaking changes.

---

## Support

For issues or questions, please refer to the main README.md file or create an issue in the project repository.
