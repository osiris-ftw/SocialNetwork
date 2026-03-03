# Testing Plan - Social Network Application

## Overview

This document outlines comprehensive testing procedures for the social network application, including manual tests, API tests, WebSocket tests, and recommendations for automated testing.

## Test Environment Setup

### Prerequisites

1. Docker containers running: `docker-compose up -d`
2. Database migrations applied: `docker-compose exec php php database/migrate.php`
3. Test data seeded: `docker-compose exec php php database/seed.php`

### Test Accounts

The seed script creates 20 test accounts. Key accounts:

| Email | Password | Role | Description |
|-------|----------|------|-------------|
| alicesmith@example.com | password123 | admin | Admin account for testing moderation |
| bobjohnson@example.com | password123 | user | Regular user #2 |
| caroldavis@example.com | password123 | user | Regular user #3 |
| davidwilson@example.com | password123 | user | Regular user #4 |

## 1. Manual Testing

### 1.1 Authentication Flow

**Test Case: User Signup**

*Steps:*
1. Open http://localhost:8000/login.html
2. Click "Sign Up" tab
3. Fill form:
   - Email: `newuser@test.com`
   - Password: `TestPass123!`
   - Full Name: `Test User`
   - Bio: `Testing account`
4. Click "Sign Up"

*Expected Results:*
- Success message: "Signup successful. Please check your email..."
- User record created in database
- Verification email sent (check logs)
- Cannot login until verified

*Verification:*
```bash
docker-compose exec mysql mysql -uroot -p -e "SELECT id, email, is_verified FROM socialnet.users WHERE email='newuser@test.com';"
```

---

**Test Case: Email Verification**

*Steps:*
1. Check Docker logs for verification token:
   ```bash
   docker-compose logs php | grep "verification_token"
   ```
2. Copy the token from the log
3. Make API call:
   ```bash
   curl -X POST http://localhost:8000/auth/verify-email \
     -H "Content-Type: application/json" \
     -d '{"token":"TOKEN_HERE"}'
   ```

*Expected Results:*
- Response: `{"message":"Email verified successfully"}`
- `is_verified` set to 1 in database

---

**Test Case: Login**

*Steps:*
1. Open http://localhost:8000/login.html
2. Enter credentials (use `alicesmith@example.com` / `password123`)
3. Click "Login"

*Expected Results:*
- Redirected to feed.html
- JWT token stored in localStorage
- User data returned with token
- Navbar shows user links

---

**Test Case: Login Rate Limiting**

*Steps:*
1. Attempt to login with wrong password 11 times rapidly

*Expected Results:*
- First 10 attempts: Invalid credentials error
- 11th attempt: Rate limit error (429)
- Wait 60 seconds, can login again

---

### 1.2 User Profile Management

**Test Case: View Profile**

*Steps:*
1. Login as `alicesmith@example.com`
2. Click on username in navbar (if implemented) or navigate to profile view

*Expected Results:*
- Profile displays:
  - Full name
  - Bio
  - Avatar
  - Cover photo
  - Post count
  - Followers count
  - Following count

---

**Test Case: Update Profile**

*Steps:*
1. Make API call:
   ```bash
   TOKEN="your_jwt_token"
   curl -X PUT http://localhost:8000/user/me \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"full_name":"Alice Smith Updated","bio":"New bio text","location":"San Francisco"}'
   ```

*Expected Results:*
- Response: `{"message":"Profile updated successfully"}`
- Database updated
- Changes reflected in next profile fetch

---

**Test Case: Upload Avatar**

*Steps:*
1. Login to feed.html
2. Prepare a test image (JPG/PNG, < 10MB)
3. Make upload request:
   ```bash
   curl -X POST http://localhost:8000/upload/avatar \
     -H "Authorization: Bearer $TOKEN" \
     -F "avatar=@/path/to/image.jpg"
   ```

*Expected Results:*
- Image uploaded to `public/uploads/avatars/`
- Response contains `avatar_url`
- User record updated with new avatar_url
- Image resized appropriately
- Thumbnail generated

---

### 1.3 Social Features

**Test Case: Create Post**

*Steps:*
1. Login to feed.html
2. Enter text in "What's on your mind?" box
3. (Optional) Upload an image
4. Click "Post"

*Expected Results:*
- Post appears at top of feed immediately
- If image uploaded, image displays in post
- Like count = 0, Comment count = 0
- Post stored in database
- Analytics event logged

---

**Test Case: Like Post**

*Steps:*
1. View feed with existing posts
2. Click "Like" button on a post

*Expected Results:*
- Like button changes appearance
- Like count increments
- Like record created in database
- Post author receives notification
- Clicking again unlikes (toggle behavior)

---

**Test Case: Comment on Post**

*Steps:*
1. Create comment via API:
   ```bash
   curl -X POST http://localhost:8000/comments \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"post_id":1,"content":"Great post!","parent_id":null}'
   ```

*Expected Results:*
- Comment created
- Comment count on post increments
- Post author receives notification
- Comment appears in post's comment list

---

**Test Case: Reply to Comment**

*Steps:*
1. Create reply:
   ```bash
   curl -X POST http://localhost:8000/comments \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"post_id":1,"content":"Thanks!","parent_id":5}'
   ```

*Expected Results:*
- Reply created with parent_id set
- Reply appears nested under parent comment
- Parent comment author receives notification
- Max 1 level of nesting enforced

---

**Test Case: Follow User**

*Steps:*
1. Follow user #2:
   ```bash
   curl -X POST http://localhost:8000/user/2/follow \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Follow relationship created
- Following count increments for current user
- Follower count increments for user #2
- User #2 receives notification
- User #2's posts appear in feed

---

**Test Case: Unfollow User**

*Steps:*
1. Unfollow user #2:
   ```bash
   curl -X DELETE http://localhost:8000/user/2/unfollow \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Follow relationship removed
- Counts decremented
- User #2's posts no longer in feed (except public ones)

---

**Test Case: Feed Pagination**

*Steps:*
1. Get first page:
   ```bash
   curl http://localhost:8000/posts/feed?limit=10&offset=0 \
     -H "Authorization: Bearer $TOKEN"
   ```
2. Get second page:
   ```bash
   curl http://localhost:8000/posts/feed?limit=10&offset=10 \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- First page returns 10 posts
- Second page returns next 10 posts
- No duplicate posts between pages
- Posts ordered by relevance (followed users + recent)

---

### 1.4 Messaging

**Test Case: Send Message via API**

*Steps:*
1. Send message to user #2:
   ```bash
   curl -X POST http://localhost:8000/messages \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"recipient_id":2,"content":"Hello from API!"}'
   ```

*Expected Results:*
- Message created in database
- Response contains `message_id`
- `is_delivered` = false initially
- Recipient receives notification
- Message appears in conversations list

---

**Test Case: Get Conversation**

*Steps:*
1. Fetch messages with user #2:
   ```bash
   curl http://localhost:8000/messages/2?limit=50 \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Returns array of messages
- Messages ordered by created_at ASC (oldest first)
- Both sent and received messages included
- Each message shows sender_id, recipient_id, content, timestamps

---

**Test Case: Mark Messages as Read**

*Steps:*
1. Mark all messages from user #2:
   ```bash
   curl -X PUT http://localhost:8000/messages/read/2 \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- All unread messages from user #2 marked as read
- Unread count decrements
- Response confirms success

---

**Test Case: Real-Time Messaging via WebSocket**

*Steps:*
1. Open messaging.html in two browsers (or incognito)
2. Login as different users in each
3. Send message from browser 1 to user in browser 2

*Expected Results:*
- Message appears in sender's chat immediately
- Message appears in recipient's chat in real-time (< 1 second)
- No page refresh needed
- Typing indicators work (if implemented)
- Unread badge updates instantly

---

**Test Case: WebSocket Reconnection**

*Steps:*
1. Open messaging.html and establish WebSocket connection
2. Restart WebSocket server:
   ```bash
   docker-compose restart websocket
   ```
3. Wait 3-5 seconds

*Expected Results:*
- Connection detects disconnect
- Auto-reconnects after 3 second delay
- Re-authenticates with JWT
- Resumes normal operation

---

### 1.5 Notifications

**Test Case: Receive Like Notification**

*Steps:*
1. Login as user #1
2. Create a post
3. Login as user #2 in another browser
4. Like user #1's post
5. Check notifications as user #1:
   ```bash
   curl http://localhost:8000/notifications \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Notification created with type "like"
- Message: "{user} liked your post"
- Data contains user_id and post_id
- is_read = false
- Notification count badge updates

---

**Test Case: Mark Notification as Read**

*Steps:*
1. Get notification ID from previous test
2. Mark as read:
   ```bash
   curl -X PUT http://localhost:8000/notifications/1/read \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Notification's is_read set to true
- Unread count decrements
- Notification style changes in UI

---

### 1.6 File Uploads

**Test Case: Upload Large Image**

*Steps:*
1. Prepare image > 10MB
2. Attempt upload:
   ```bash
   curl -X POST http://localhost:8000/upload/image \
     -H "Authorization: Bearer $TOKEN" \
     -F "image=@large_image.jpg"
   ```

*Expected Results:*
- Error: File too large
- HTTP 400 status
- Clear error message

---

**Test Case: Upload Invalid File Type**

*Steps:*
1. Attempt to upload .exe or .php file

*Expected Results:*
- Error: Invalid file type
- File not saved
- Only allowed: JPG, PNG, GIF, MP4, MOV, AVI

---

**Test Case: Image Processing**

*Steps:*
1. Upload large image (5000x3000px):
   ```bash
   curl -X POST http://localhost:8000/upload/image \
     -H "Authorization: Bearer $TOKEN" \
     -F "image=@large_photo.jpg"
   ```

*Expected Results:*
- Image resized to max 2000px width
- Aspect ratio maintained
- Thumbnail generated (300x300px)
- EXIF data stripped
- Both URLs returned in response

---

### 1.7 Admin/Moderation

**Test Case: Report Content**

*Steps:*
1. Login as regular user
2. Report a post (via API since UI may not exist):
   ```bash
   curl -X POST http://localhost:8000/reports \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"content_type":"post","content_id":5,"reason":"spam"}'
   ```

*Expected Results:*
- Report created with status "pending"
- Admin can view in /admin/reports
- Reporter_id and reported_id recorded

---

**Test Case: Review Report as Admin**

*Steps:*
1. Login as `alicesmith@example.com` (admin)
2. Open admin.html
3. Click "Reports" tab
4. View pending reports
5. Click "Mark as Reviewed" on a report

*Expected Results:*
- Report status changes to "reviewed"
- Report moves to reviewed filter
- Admin can take action (ban user, delete content)

---

**Test Case: Ban User**

*Steps:*
1. As admin, ban user #5:
   ```bash
   curl -X POST http://localhost:8000/admin/users/5/ban \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"reason":"Spamming"}'
   ```

*Expected Results:*
- User's is_banned = 1
- ban_reason set
- User cannot login
- User's posts hidden from feed
- User receives 403 on all authenticated requests

---

**Test Case: Delete Post as Admin**

*Steps:*
1. Delete any post:
   ```bash
   curl -X DELETE http://localhost:8000/admin/posts/10 \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Post removed from database
- Comments on post also deleted (cascade)
- Post no longer appears in feeds
- Analytics event logged

---

**Test Case: View Platform Statistics**

*Steps:*
1. As admin, view stats:
   ```bash
   curl http://localhost:8000/admin/stats \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Returns total_users, total_posts, total_comments, etc.
- Accurate counts from database
- Pending reports count shown

---

**Test Case: View Analytics**

*Steps:*
1. Get last 30 days analytics:
   ```bash
   curl http://localhost:8000/admin/analytics?days=30 \
     -H "Authorization: Bearer $TOKEN"
   ```

*Expected Results:*
- Daily stats for each day
- Events grouped by type (login, post_created, etc.)
- Top users by activity
- Chart-ready data format

---

## 2. API Integration Tests

### 2.1 Full User Journey

**Test: Complete User Lifecycle**

```bash
#!/bin/bash

# 1. Signup
SIGNUP_RESP=$(curl -X POST http://localhost:8000/auth/signup \
  -H "Content-Type: application/json" \
  -d '{"email":"journey@test.com","password":"Pass123!","full_name":"Journey Test"}')
echo "Signup: $SIGNUP_RESP"

# 2. Verify (extract token from logs manually)
# curl -X POST http://localhost:8000/auth/verify-email -d '{"token":"..."}'

# 3. Login
LOGIN_RESP=$(curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alicesmith@example.com","password":"password123"}')
TOKEN=$(echo $LOGIN_RESP | grep -o '"token":"[^"]*' | cut -d'"' -f4)
echo "Token: $TOKEN"

# 4. Get profile
curl http://localhost:8000/user/me \
  -H "Authorization: Bearer $TOKEN"

# 5. Create post
POST_RESP=$(curl -X POST http://localhost:8000/posts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Integration test post","visibility":"public"}')
echo "Post: $POST_RESP"

# 6. Get feed
curl http://localhost:8000/posts/feed \
  -H "Authorization: Bearer $TOKEN"

# 7. Follow user
curl -X POST http://localhost:8000/user/2/follow \
  -H "Authorization: Bearer $TOKEN"

# 8. Send message
curl -X POST http://localhost:8000/messages \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"recipient_id":2,"content":"Test message"}'

# 9. Get notifications
curl http://localhost:8000/notifications \
  -H "Authorization: Bearer $TOKEN"
```

*Expected: All steps succeed with 200/201 responses*

---

### 2.2 Authorization Tests

**Test: Access Protected Endpoint Without Token**

```bash
curl http://localhost:8000/posts/feed
```

*Expected: 401 Unauthorized*

---

**Test: Access Admin Endpoint as Regular User**

```bash
# Login as regular user
curl http://localhost:8000/admin/stats \
  -H "Authorization: Bearer $REGULAR_USER_TOKEN"
```

*Expected: 403 Forbidden*

---

**Test: Delete Other User's Post**

```bash
# User 1 tries to delete User 2's post
curl -X DELETE http://localhost:8000/posts/15 \
  -H "Authorization: Bearer $USER1_TOKEN"
```

*Expected: 403 Forbidden (unless admin)*

---

### 2.3 Rate Limiting Tests

**Test: Exceed Login Rate Limit**

```bash
for i in {1..15}; do
  curl -X POST http://localhost:8000/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"wrong"}'
  echo "Attempt $i"
done
```

*Expected: First 10 fail with 401, attempts 11-15 fail with 429*

---

**Test: Exceed Post Creation Rate Limit**

```bash
for i in {1..25}; do
  curl -X POST http://localhost:8000/posts \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"content\":\"Test post $i\",\"visibility\":\"public\"}"
done
```

*Expected: First 20 succeed, attempts 21-25 return 429*

---

## 3. WebSocket Tests

### 3.1 Connection Tests

**Test: Connect Without Token**

```javascript
const ws = new WebSocket('ws://localhost:8081');
```

*Expected: Connection closes with error "Authentication required"*

---

**Test: Connect With Invalid Token**

```javascript
const ws = new WebSocket('ws://localhost:8081?token=invalid_token');
ws.onmessage = (e) => console.log(JSON.parse(e.data));
```

*Expected: Error message received, connection closes*

---

**Test: Connect With Valid Token**

```javascript
const token = 'VALID_JWT_TOKEN';
const ws = new WebSocket(`ws://localhost:8081?token=${token}`);
ws.onopen = () => console.log('Connected');
ws.onmessage = (e) => console.log('Received:', JSON.parse(e.data));
```

*Expected: Connection established, receives pong on ping*

---

### 3.2 Messaging Tests

**Test: Send Message via WebSocket**

```javascript
const ws = new WebSocket(`ws://localhost:8081?token=${token}`);
ws.onopen = () => {
  ws.send(JSON.stringify({
    type: 'message',
    recipient_id: 2,
    content: 'WebSocket test message'
  }));
};
ws.onmessage = (e) => {
  const msg = JSON.parse(e.data);
  if (msg.type === 'message_sent') {
    console.log('Message sent, ID:', msg.message_id);
  }
};
```

*Expected:*
- Receives `message_sent` confirmation
- Recipient receives `message` event in real-time
- Message saved to database

---

**Test: Typing Indicator**

```javascript
// User 1
ws.send(JSON.stringify({
  type: 'typing',
  recipient_id: 2
}));

// User 2 should receive typing event
```

*Expected: User 2's WebSocket receives typing notification*

---

**Test: Multiple Concurrent Connections**

*Steps:*
1. Open 10 browser tabs
2. Login as different users in each
3. Establish WebSocket connections
4. Send messages between users

*Expected:*
- All connections maintained
- Messages delivered to correct recipients
- No messages lost or duplicated
- Server handles concurrent load

---

## 4. Performance Tests

### 4.1 Load Testing

**Tool Recommendation:** Apache Bench or Artillery

**Test: Feed Endpoint**

```bash
# 1000 requests, 10 concurrent
ab -n 1000 -c 10 \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/posts/feed
```

*Targets:*
- Average response time < 200ms
- 95th percentile < 500ms
- 0% error rate
- Can handle 100 requests/second

---

**Test: WebSocket Connections**

*Scenario:* 100 simultaneous WebSocket connections sending messages

*Expected:*
- All connections maintained
- Message delivery time < 100ms
- Server memory stable
- No connection drops

---

### 4.2 Database Performance

**Test: Complex Feed Query**

```sql
EXPLAIN SELECT p.*, u.full_name, u.avatar_url,
  (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
  (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
FROM posts p
INNER JOIN users u ON p.user_id = u.id
LEFT JOIN follows f ON f.following_id = p.user_id AND f.follower_id = 1
WHERE p.is_deleted = 0
  AND (p.visibility = 'public' OR (p.visibility = 'followers' AND f.id IS NOT NULL))
ORDER BY f.id IS NOT NULL DESC, p.created_at DESC
LIMIT 20;
```

*Expected:*
- Uses indexes efficiently
- No full table scans
- Query time < 50ms
- EXPLAIN shows "Using index"

---

## 5. Security Tests

### 5.1 SQL Injection

**Test: Inject SQL in Login**

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com OR 1=1--","password":"anything"}'
```

*Expected: Safe error, no SQL execution, PDO prepared statements prevent injection*

---

### 5.2 XSS Prevention

**Test: Script in Post Content**

```bash
curl -X POST http://localhost:8000/posts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"<script>alert(\"XSS\")</script>","visibility":"public"}'
```

*Expected:*
- Content stored as-is in database
- Frontend escapes HTML when rendering
- Script does not execute in browser

---

### 5.3 CSRF Protection

**Test: Cross-Origin Request**

*Note: Current implementation doesn't have CSRF tokens (stateless JWT)*

*Recommendation:* Add SameSite cookie flags or CSRF tokens for production

---

### 5.4 File Upload Validation

**Test: Upload PHP File**

```bash
echo "<?php system($_GET['cmd']); ?>" > malicious.php
curl -X POST http://localhost:8000/upload/image \
  -H "Authorization: Bearer $TOKEN" \
  -F "image=@malicious.php"
```

*Expected: Rejected with "Invalid file type"*

---

**Test: Upload File with Embedded Script**

*Expected: EXIF stripping removes malicious metadata*

---

## 6. Automated Testing Recommendations

### 6.1 Unit Tests (PHPUnit)

Create `tests/Unit/` directory:

**UserTest.php**
```php
<?php
use PHPUnit\Framework\TestCase;
use App\Models\User;

class UserTest extends TestCase
{
    public function testPasswordHashing()
    {
        $user = User::create([
            'email' => 'test@test.com',
            'password' => 'password123',
            'full_name' => 'Test User'
        ]);
        
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }
    
    public function testFollowUser()
    {
        $user1 = User::find(1);
        $user2 = User::find(2);
        
        $user1->follow($user2->id);
        
        $this->assertTrue($user1->isFollowing($user2->id));
        $this->assertEquals(1, $user2->getFollowersCount());
    }
}
```

---

### 6.2 Integration Tests

**MessagingFlowTest.php**
```php
<?php
class MessagingFlowTest extends TestCase
{
    public function testSendAndReceiveMessage()
    {
        // Login
        $token = $this->login('user1@test.com', 'password');
        
        // Send message
        $response = $this->post('/messages', [
            'recipient_id' => 2,
            'content' => 'Test message'
        ], ['Authorization' => "Bearer $token"]);
        
        $this->assertEquals(201, $response->status);
        $messageId = $response->json['message_id'];
        
        // Verify message exists
        $msg = Message::find($messageId);
        $this->assertEquals('Test message', $msg->content);
        $this->assertEquals(2, $msg->recipient_id);
    }
}
```

---

### 6.3 End-to-End Tests (Selenium/Playwright)

**feed.test.js** (Playwright example)
```javascript
test('User can create post and see in feed', async ({ page }) => {
  // Login
  await page.goto('http://localhost:8000/login.html');
  await page.fill('#login-email', 'alice@example.com');
  await page.fill('#login-password', 'password123');
  await page.click('#login-submit');
  
  // Wait for feed
  await page.waitForURL('**/feed.html');
  
  // Create post
  const content = `Test post ${Date.now()}`;
  await page.fill('#post-content', content);
  await page.click('#post-submit');
  
  // Verify post appears
  await page.waitForTimeout(1000);
  const postExists = await page.locator(`text=${content}`).isVisible();
  expect(postExists).toBeTruthy();
});
```

---

### 6.4 CI/CD Integration

**GitHub Actions** (.github/workflows/test.yml)
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Start services
        run: docker-compose up -d
      
      - name: Install dependencies
        run: docker-compose exec -T php composer install
      
      - name: Run migrations
        run: docker-compose exec -T php php database/migrate.php
      
      - name: Run PHPUnit tests
        run: docker-compose exec -T php vendor/bin/phpunit
      
      - name: Run integration tests
        run: ./tests/integration.sh
```

---

## 7. Test Checklist

Use this checklist for regression testing:

### Authentication
- [ ] User can signup
- [ ] Email verification works
- [ ] User can login
- [ ] Invalid credentials rejected
- [ ] Banned users cannot login
- [ ] JWT token works for authentication
- [ ] Token expiration handled

### User Management
- [ ] Profile can be viewed
- [ ] Profile can be updated
- [ ] Avatar upload works
- [ ] Cover photo upload works
- [ ] User can be followed
- [ ] User can be unfollowed
- [ ] Followers list accurate
- [ ] Following list accurate

### Posts
- [ ] Post can be created
- [ ] Post with image works
- [ ] Post appears in feed
- [ ] Post can be liked
- [ ] Post can be unliked
- [ ] Post can be deleted (by owner)
- [ ] Visibility settings work
- [ ] Feed pagination works

### Comments
- [ ] Comment can be added
- [ ] Reply to comment works
- [ ] Comment count updates
- [ ] Comment can be deleted

### Messaging
- [ ] Message can be sent via API
- [ ] Message appears in conversation
- [ ] Conversations list accurate
- [ ] Unread count correct
- [ ] Mark as read works
- [ ] WebSocket delivers in real-time
- [ ] Typing indicators work

### Notifications
- [ ] Like notifications created
- [ ] Comment notifications created
- [ ] Follow notifications created
- [ ] Message notifications created
- [ ] Unread count accurate
- [ ] Mark as read works
- [ ] Mark all as read works

### File Uploads
- [ ] Image upload works
- [ ] Image resized correctly
- [ ] Thumbnail generated
- [ ] File size limits enforced
- [ ] File type validation works
- [ ] EXIF data stripped

### Admin
- [ ] Reports can be viewed
- [ ] Report status can be updated
- [ ] User can be banned
- [ ] User can be unbanned
- [ ] Post can be deleted (admin)
- [ ] Comment can be deleted (admin)
- [ ] Statistics accurate
- [ ] Analytics data correct

### Security
- [ ] SQL injection prevented
- [ ] XSS escaped
- [ ] File upload sanitized
- [ ] Rate limiting works
- [ ] Authorization enforced
- [ ] Admin-only endpoints protected

### Performance
- [ ] Feed loads < 200ms
- [ ] WebSocket latency < 100ms
- [ ] 100 concurrent users supported
- [ ] Database queries optimized
- [ ] No memory leaks

---

## 8. Bug Reporting Template

When finding bugs during testing:

```markdown
**Title:** Brief description

**Environment:**
- Docker version:
- Browser (if applicable):
- User role:

**Steps to Reproduce:**
1. Step 1
2. Step 2
3. Step 3

**Expected Result:**
What should happen

**Actual Result:**
What actually happens

**Screenshots/Logs:**
Attach relevant logs or screenshots

**Severity:**
- [ ] Critical (blocks core functionality)
- [ ] Major (important feature broken)
- [ ] Minor (small issue)
- [ ] Trivial (cosmetic)
```

---

## 9. Test Results Documentation

After running tests, document results:

| Test Suite | Total Tests | Passed | Failed | Success Rate |
|------------|-------------|--------|--------|--------------|
| Authentication | 7 | - | - | - |
| User Management | 8 | - | - | - |
| Posts | 9 | - | - | - |
| Messaging | 7 | - | - | - |
| WebSocket | 5 | - | - | - |
| Admin | 8 | - | - | - |
| Security | 5 | - | - | - |
| **Total** | **49** | **-** | **-** | **-** |

---

## 10. Continuous Testing

**Daily:**
- Run automated test suite
- Check error logs
- Monitor WebSocket connections

**Weekly:**
- Manual exploratory testing
- Performance benchmarks
- Security scan

**Before Release:**
- Full regression test
- Load testing
- User acceptance testing

---

This testing plan provides comprehensive coverage for all features of the social network application. Implement automated tests gradually, starting with critical paths (auth, messaging) and expanding coverage over time.
