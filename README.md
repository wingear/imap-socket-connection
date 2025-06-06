# IMAP Socket Connection

Lightweight PHP library for working with IMAP servers via socket connection

[![Latest Stable Version](https://poser.pugx.org/your-username/imap-socket-connection/v/stable)](https://packagist.org/packages/your-username/imap-socket-connection)
[![Total Downloads](https://poser.pugx.org/your-username/imap-socket-connection/downloads)](https://packagist.org/packages/your-username/imap-socket-connection)
[![License](https://poser.pugx.org/your-username/imap-socket-connection/license)](https://packagist.org/packages/your-username/imap-socket-connection)
[![PHP Version Require](https://poser.pugx.org/your-username/imap-socket-connection/require/php)](https://packagist.org/packages/your-username/imap-socket-connection)

## Description

This library provides a simple and efficient way to connect to IMAP servers and perform basic email operations without using the php-imap extension.

## Features

- ✅ Direct socket connection to IMAP servers
- ✅ Folder management (list, selection)
- ✅ Search and receive messages
- ✅ Work with headers and message bodies
- ✅ Attachment handling
- ✅ Manage message flags (read/unread)
- ✅ Delete messages
- ✅ Decoding various types of encodings

## Requirements

- PHP 7.4 or higher
- Extension `sockets` (usually enabled by default)

## Installation

### Composer

```bash
composer require wingear/imap-socket-connection
```

### Manual

Download file and require:

```php
require_once 'src/IMAPSocketConnection.php';
```

## Example

```php
<?php
require_once 'IMAPSocketConnection.php';

use wingear\IMAPSocketConnection;

// Create socket connection. EXAMPLE! Use Google documentation for more correct connection to gmail
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, 'imap.gmail.com', 993);

// Creating IMAP connection
$imap = new IMAPSocketConnection($socket);

// Folder selecting
$imap->selectFolder('INBOX');

// Searching all emails
$messages = $imap->searchMails();

// Get email's headers
$headers = $imap->fetchHeaders($messages[0]);

// Get email's body
$body = $imap->fetchBody($messages[0]);

// Closing connection
$imap->close();
```

## API documentation

### Create connection

```php
$imap = new IMAPConnection($socket);
```

### Main methods

#### Folders managing

```php
// Select folder
$imap->selectFolder('INBOX');

// Get folder list
$folders = $imap->listFolders();

// Get folder info
$info = $imap->getFolderInfo('INBOX');
```

#### Messages

```php
// Search messages
$messages = $imap->searchMails('UNSEEN'); // Unread messages
$messages = $imap->searchMails('ALL');    // All messages

// Get headers
$headers = $imap->fetchHeaders($messageId);
$headers = $imap->fetchHeaders($messageId, ['From', 'Subject', 'Date']);

// Get message's body
$body = $imap->fetchBody($messageId);
$body = $imap->fetchBody($messageId, false); // Without PEEK (will mark as read)

// Get full message
$fullMessage = $imap->fetchMessage($messageId);

// Get text part
$text = $imap->getMessageText($messageId);
// Returns: ['plain' => '...', 'html' => '...']

// Get exact header fields
$from = $imap->getFrom($messageId);
$to = $imap->getTo($messageId);
$date = $imap->getDate($messageId);
$fields = $imap->getHeaderFields($messageId, ['From', 'Subject']);
```

#### Flags

```php
// Mark as read
$imap->markAsRead([1, 2, 3]);

// Mark as unread
$imap->markAsUnread([1, 2, 3]);

// Delete message(s)
$imap->deleteMessages([1, 2, 3]);
```

#### Attachments

```php
// Set directory to save attachments
$imap->setAttachmentsDirectory('/path/to/attachments/');

// Get attachments list
$attachments = $imap->getAttachments($messageId);
// Returns: [['filename' => '...', 'data' => '...', 'content_type' => '...'], ...]

// Save attachment
$imap->saveAttachment($messageId, 'document.pdf');
```

### Executing any commands

```php
// Execute any IMAP command
$response = $imap->executeCommand('CAPABILITY');
$response = $imap->executeCommand('LOGIN "user" "password"');
```

## Examples of use

### Example 1: Receiving unread emails

```php
<?php
require_once 'vendor/autoload.php';

use wingear\IMAPSocketConnection;

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, 'imap.example.com', 143);

$imap = new IMAPSocketConnection($socket);
$imap->executeCommand('LOGIN "user@example.com" "password"');
$imap->selectFolder('INBOX');

$unreadMessages = $imap->searchMails('UNSEEN');

foreach ($unreadMessages as $messageId) {
    $from = $imap->getFrom($messageId);
    $subject = $imap->getHeaderFields($messageId, ['Subject']);
    $text = $imap->getMessageText($messageId);
    
    echo "From: $from\n";
    echo "Subject: " . $subject['Subject'] . "\n";
    echo "Text: " . substr($text['plain'], 0, 100) . "...\n\n";
}

$imap->close();
```

### Example 2: Saving attachments

```php
<?php
$imap->setAttachmentsDirectory('./downloads/');
$attachments = $imap->getAttachments($messageId);

foreach ($attachments as $attachment) {
    echo "Found attachment: " . $attachment['filename'] . "\n";
    $imap->saveAttachment($messageId, $attachment['filename']);
}
```

## Security

⚠️ **Important**: Never store passwords in plaintext in code. Use environment variables or secure storage for sensitive data.

```php
// Good
$password = $_ENV['EMAIL_PASSWORD'];

// Bad
$password = 'my-secret-password';
```

## SSL/TLS support

For secure connections, use the SSL context:

```php
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$socket = stream_socket_client('ssl://imap.gmail.com:993', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
```

## License

This project is distributed under the MIT license. Details are in the [LICENSE](LICENSE) file.

## Support

If you have questions or suggestions:

- Create an Issue on GitHub
- Send an email to: faridnzv@gmail.com
