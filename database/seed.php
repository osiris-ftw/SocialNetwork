<?php

/**
 * Database Seed Script
 * Creates test data: 20 users, 50 posts, follows, comments, likes, and messages
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

try {
    $host = $_ENV['DB_HOST'];
    $database = $_ENV['DB_DATABASE'];
    $username = $_ENV['DB_USERNAME'];
    $password = $_ENV['DB_PASSWORD'];

    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "Starting database seeding...\n";
    echo str_repeat('-', 50) . "\n";

    // Clear existing data
    echo "Clearing existing data...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE events");
    $pdo->exec("TRUNCATE TABLE notifications");
    $pdo->exec("TRUNCATE TABLE messages");
    $pdo->exec("TRUNCATE TABLE reports");
    $pdo->exec("TRUNCATE TABLE likes");
    $pdo->exec("TRUNCATE TABLE comments");
    $pdo->exec("TRUNCATE TABLE posts");
    $pdo->exec("TRUNCATE TABLE follows");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Sample data
    $firstNames = ['Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Henry', 'Ivy', 'Jack', 'Kate', 'Leo', 'Maya', 'Noah', 'Olivia', 'Paul', 'Quinn', 'Ruby', 'Sam', 'Tina'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
    $bios = [
        'Tech enthusiast and coffee lover ☕',
        'Digital nomad exploring the world 🌍',
        'Photographer capturing moments 📸',
        'Fitness junkie and health advocate 💪',
        'Bookworm and aspiring writer 📚',
        'Music lover and concert goer 🎵',
        'Foodie always seeking new flavors 🍕',
        'Nature lover and hiker 🌲',
        'Gamer and streamer 🎮',
        'Artist creating digital art 🎨'
    ];
    $locations = ['New York, NY', 'San Francisco, CA', 'London, UK', 'Tokyo, Japan', 'Paris, France', 'Berlin, Germany', 'Toronto, Canada', 'Sydney, Australia', 'Singapore', 'Dubai, UAE'];

    // Hash password (same for all test users: password123)
    $passwordHash = password_hash('password123', PASSWORD_ARGON2ID);

    // Create 20 users
    echo "Creating 20 users...\n";
    $userIds = [];
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, email_verified_at, bio, location, website, is_admin)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
    ");

    for ($i = 0; $i < 20; $i++) {
        $firstName = $firstNames[$i];
        $lastName = $lastNames[$i % count($lastNames)];
        $username = strtolower($firstName . $lastName . ($i > 9 ? $i : ''));
        $email = $username . '@example.com';
        $bio = $bios[$i % count($bios)];
        $location = $locations[$i % count($locations)];
        $website = "https://{$username}.example.com";
        $isAdmin = ($i === 0) ? 1 : 0; // First user is admin

        $stmt->execute([$username, $email, $passwordHash, $bio, $location, $website, $isAdmin]);
        $userIds[] = $pdo->lastInsertId();
    }
    echo "✓ Created 20 users\n";

    // Create follows (each user follows 3-7 random other users)
    echo "Creating follow relationships...\n";
    $stmt = $pdo->prepare("INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)");
    $followCount = 0;

    foreach ($userIds as $userId) {
        $followersToCreate = rand(3, 7);
        $availableUsers = array_diff($userIds, [$userId]);
        $usersToFollow = array_rand(array_flip($availableUsers), min($followersToCreate, count($availableUsers)));
        
        if (!is_array($usersToFollow)) {
            $usersToFollow = [$usersToFollow];
        }

        foreach ($usersToFollow as $followingId) {
            $stmt->execute([$userId, $followingId]);
            $followCount++;
        }
    }
    echo "✓ Created {$followCount} follow relationships\n";

    // Create 50 posts
    echo "Creating 50 posts...\n";
    $postContents = [
        "Just finished an amazing workout! Feeling energized 💪",
        "Beautiful sunset today. Nature never ceases to amaze me 🌅",
        "Check out my latest project! What do you think?",
        "Coffee and code - the perfect combination ☕💻",
        "Traveled to a new city this weekend. So many new experiences!",
        "Reading an incredible book right now. Highly recommend!",
        "Made some delicious pasta for dinner tonight 🍝",
        "Working on improving my photography skills 📸",
        "Just launched my new website! Link in bio",
        "Great conversation with friends today. Feeling grateful 🙏",
        "Trying out a new recipe. Wish me luck!",
        "The weather is perfect for a long walk 🚶",
        "Finished a challenging project at work. Feels great!",
        "Discovered an amazing new artist. Check them out!",
        "Movie night! Any recommendations?",
        "Started learning a new language today",
        "Gym session complete. Time for a protein shake!",
        "Can't believe how fast this year is going by",
        "Just adopted a new plant for my collection 🌱",
        "Weekend plans: relax and recharge"
    ];

    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, content, media_type, visibility)
        VALUES (?, ?, ?, ?)
    ");

    $postIds = [];
    for ($i = 0; $i < 50; $i++) {
        $userId = $userIds[array_rand($userIds)];
        $content = $postContents[$i % count($postContents)];
        $mediaType = (rand(1, 10) > 7) ? 'image' : 'none'; // 30% have images
        $visibility = (rand(1, 10) > 9) ? 'followers' : 'public'; // 10% followers-only

        $stmt->execute([$userId, $content, $mediaType, $visibility]);
        $postIds[] = $pdo->lastInsertId();
    }
    echo "✓ Created 50 posts\n";

    // Create comments (100-150 comments, some nested)
    echo "Creating comments...\n";
    $commentContents = [
        "Great post!",
        "Love this! 😍",
        "Thanks for sharing!",
        "This is amazing!",
        "Totally agree with this",
        "Wow, impressive!",
        "This made my day 😊",
        "Can you share more details?",
        "Awesome work!",
        "I needed to see this today"
    ];

    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, user_id, parent_comment_id, content)
        VALUES (?, ?, ?, ?)
    ");

    $commentIds = [];
    $commentCount = 0;

    // Create top-level comments
    for ($i = 0; $i < 120; $i++) {
        $postId = $postIds[array_rand($postIds)];
        $userId = $userIds[array_rand($userIds)];
        $content = $commentContents[array_rand($commentContents)];

        $stmt->execute([$postId, $userId, null, $content]);
        $commentIds[] = $pdo->lastInsertId();
        $commentCount++;
    }

    // Create nested comments (replies)
    for ($i = 0; $i < 30; $i++) {
        $parentCommentId = $commentIds[array_rand($commentIds)];
        $userId = $userIds[array_rand($userIds)];
        $content = $commentContents[array_rand($commentContents)];

        // Get post_id from parent comment
        $parentStmt = $pdo->prepare("SELECT post_id FROM comments WHERE id = ?");
        $parentStmt->execute([$parentCommentId]);
        $postId = $parentStmt->fetchColumn();

        $stmt->execute([$postId, $userId, $parentCommentId, $content]);
        $commentCount++;
    }

    echo "✓ Created {$commentCount} comments\n";

    // Create likes (each post gets 1-10 likes)
    echo "Creating likes...\n";
    $stmt = $pdo->prepare("INSERT IGNORE INTO likes (user_id, post_id) VALUES (?, ?)");
    $likeCount = 0;

    foreach ($postIds as $postId) {
        $likesToCreate = rand(1, 10);
        $usersWhoLiked = array_rand(array_flip($userIds), min($likesToCreate, count($userIds)));
        
        if (!is_array($usersWhoLiked)) {
            $usersWhoLiked = [$usersWhoLiked];
        }

        foreach ($usersWhoLiked as $userId) {
            $stmt->execute([$userId, $postId]);
            $likeCount++;
        }
    }
    echo "✓ Created {$likeCount} likes\n";

    // Update like_count and comment_count on posts
    $pdo->exec("
        UPDATE posts p
        SET like_count = (SELECT COUNT(*) FROM likes WHERE post_id = p.id),
            comment_count = (SELECT COUNT(*) FROM comments WHERE post_id = p.id)
    ");

    // Create messages (30 conversations)
    echo "Creating messages...\n";
    $messageContents = [
        "Hey! How are you doing?",
        "Thanks for following me!",
        "Did you see my latest post?",
        "Let's catch up soon!",
        "Great to connect with you!",
        "I loved your recent post about traveling",
        "Do you want to collaborate on a project?",
        "Thanks for the comment!",
        "How was your weekend?",
        "Looking forward to chatting more"
    ];

    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, recipient_id, content, is_delivered, is_read)
        VALUES (?, ?, ?, ?, ?)
    ");

    $messageCount = 0;
    for ($i = 0; $i < 30; $i++) {
        $senderId = $userIds[array_rand($userIds)];
        $recipientId = $userIds[array_rand($userIds)];
        
        if ($senderId === $recipientId) {
            continue; // Skip self-messages
        }

        $content = $messageContents[array_rand($messageContents)];
        $isDelivered = rand(0, 1);
        $isRead = $isDelivered ? rand(0, 1) : 0;

        $stmt->execute([$senderId, $recipientId, $content, $isDelivered, $isRead]);
        $messageCount++;
    }
    echo "✓ Created {$messageCount} messages\n";

    // Create some notifications
    echo "Creating notifications...\n";
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, actor_id, type, entity_type, entity_id, is_read)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $notificationCount = 0;
    // Create some follow notifications
    for ($i = 0; $i < 20; $i++) {
        $userId = $userIds[array_rand($userIds)];
        $actorId = $userIds[array_rand($userIds)];
        $stmt->execute([$userId, $actorId, 'new_follower', 'user', $actorId, rand(0, 1)]);
        $notificationCount++;
    }

    // Create some like notifications
    for ($i = 0; $i < 30; $i++) {
        $postId = $postIds[array_rand($postIds)];
        $likerUserId = $userIds[array_rand($userIds)];
        
        // Get post owner
        $ownerStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $ownerStmt->execute([$postId]);
        $ownerId = $ownerStmt->fetchColumn();
        
        if ($ownerId !== $likerUserId) {
            $stmt->execute([$ownerId, $likerUserId, 'new_like', 'post', $postId, rand(0, 1)]);
            $notificationCount++;
        }
    }

    echo "✓ Created {$notificationCount} notifications\n";

    // Create some events for analytics
    echo "Creating analytics events...\n";
    $eventStmt = $pdo->prepare("
        INSERT INTO events (user_id, event_type, entity_type, entity_id, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");

    $eventCount = 0;
    foreach ($userIds as $userId) {
        // User signup event
        $eventStmt->execute([$userId, 'user_signup', 'none', null, '127.0.0.1']);
        $eventCount++;
    }

    // Post created events
    foreach ($postIds as $postId) {
        $ownerStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $ownerStmt->execute([$postId]);
        $ownerId = $ownerStmt->fetchColumn();
        
        $eventStmt->execute([$ownerId, 'post_created', 'post', $postId, '127.0.0.1']);
        $eventCount++;
    }

    echo "✓ Created {$eventCount} analytics events\n";

    echo str_repeat('-', 50) . "\n";
    echo "✓ Database seeding completed successfully!\n\n";
    echo "Summary:\n";
    echo "  - 20 users created (admin: alicesmith@example.com)\n";
    echo "  - {$followCount} follow relationships\n";
    echo "  - 50 posts created\n";
    echo "  - {$commentCount} comments created\n";
    echo "  - {$likeCount} likes created\n";
    echo "  - {$messageCount} messages created\n";
    echo "  - {$notificationCount} notifications created\n";
    echo "  - {$eventCount} analytics events created\n";
    echo "\nAll users have password: password123\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
