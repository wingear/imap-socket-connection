<?php

/**
 * IMAP Socket Connection Class
 *
 * Lightweight PHP library for working with IMAP servers via socket connection
 *
 * @author Farid Niiazov <faridnzv@gmail.com>
 * @version 1.0.0
 * @license MIT
 */
class IMAPSocketConnection
{
    private $socket;
    private $tagCounter = 1;
    protected $attachmentsDirectory;

    public function __construct($socket)
    {
        $this->socket = $socket;
    }

    /**
     * Execute IMAP command
     *
     * @param string $command example: "SELECT INBOX"
     * @return string
     */
    public function executeCommand(string $command): string
    {
        $tag = 'A' . str_pad($this->tagCounter++, 3, '0', STR_PAD_LEFT);
        $fullCommand = $tag . ' ' . $command;

        fwrite($this->socket, $fullCommand . "\r\n");
        fflush($this->socket);

        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strpos($line, $tag) === 0) {
                break;
            }
        }

        return trim($response);
    }

    /**
     * @param string $folder
     * @return string
     */
    public function selectFolder(string $folder = 'INBOX'): string
    {
        return $this->executeCommand("SELECT $folder");
    }

    /**
     * Get folder list
     *
     * @return array
     */
    public function listFolders(): array
    {
        $response = $this->executeCommand('LIST "" "*"');
        $folders = [];

        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            if (preg_match('/\* LIST \([^)]*\) "([^"]*)" "([^"]*)"/', $line, $matches)) {
                $folders[] = $matches[2];
            }
        }

        return $folders;
    }

    /**
     * Search emails by criteria
     *
     * @param string $criteria
     * @return array|false|string[]
     */
    public function searchMails(string $criteria = 'ALL')
    {
        $response = $this->executeCommand("SEARCH $criteria");

        if (preg_match('/\* SEARCH (.+)/', $response, $matches)) {
            return array_filter(explode(' ', trim($matches[1])));
        }

        return [];
    }

    /**
     * @param int $messageId
     * @param null $headers
     * @return string
     */
    public function fetchHeaders(int $messageId, $headers = null): string
    {
        if ($headers) {
            $headerList = is_array($headers) ? implode(' ', $headers) : $headers;
            return $this->executeCommand("FETCH $messageId (BODY.PEEK[HEADER.FIELDS ($headerList)])");
        }
        return $this->executeCommand("FETCH $messageId (ENVELOPE)");
    }

    /**
     * @param int $messageId
     * @param bool $peek
     * @return string
     */
    public function fetchBody(int $messageId, bool $peek = true): string
    {
        $command = $peek ? "BODY.PEEK[TEXT]" : "BODY[TEXT]";
        return $this->executeCommand("FETCH $messageId ($command)");
    }

    /**
     * Get full message
     *
     * @param int $messageId
     * @param bool $peek
     * @return string
     */
    public function fetchMessage(int $messageId, bool $peek = true): string
    {
        $command = $peek ? "BODY.PEEK[]" : "RFC822";
        return $this->executeCommand("FETCH $messageId ($command)");
    }

    /**
     * @param string $folder
     * @return array
     */
    public function getFolderInfo(string $folder = 'INBOX'): array
    {
        $folder = $this->quoteFolderName($folder);
        $response = $this->executeCommand("STATUS $folder (MESSAGES UNSEEN RECENT UIDNEXT UIDVALIDITY)");

        $info = [];
        if (preg_match('/MESSAGES (\d+)/', $response, $matches)) {
            $info['messages'] = (int)$matches[1];
        }
        if (preg_match('/UNSEEN (\d+)/', $response, $matches)) {
            $info['unseen'] = (int)$matches[1];
        }
        if (preg_match('/RECENT (\d+)/', $response, $matches)) {
            $info['recent'] = (int)$matches[1];
        }
        if (preg_match('/UIDNEXT (\d+)/', $response, $matches)) {
            $info['uidnext'] = (int)$matches[1];
        }
        if (preg_match('/UIDVALIDITY (\d+)/', $response, $matches)) {
            $info['uidvalidity'] = (int)$matches[1];
        }

        return $info;
    }

    /**
     * Escape folder name
     *
     * @param string $folder
     * @return string
     */
    private function quoteFolderName(string $folder): string
    {
        if (preg_match('/[\s"\\\\]/', $folder)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $folder) . '"';
        }
        return $folder;
    }

    /**
     * @param array|string $messageIds
     * @return string
     */
    public function markAsRead($messageIds): string
    {
        $ids = is_array($messageIds) ? implode(',', $messageIds) : $messageIds;
        return $this->executeCommand("STORE $ids +FLAGS (\\Seen)");
    }

    /**
     * @param array|string $messageIds
     * @return string
     */
    public function markAsUnread($messageIds): string
    {
        $ids = is_array($messageIds) ? implode(',', $messageIds) : $messageIds;
        return $this->executeCommand("STORE $ids +FLAGS (\\Unseen)");
    }

    /**
     * @param array|string $messageIds
     * @return string
     */
    public function deleteMessages($messageIds): string
    {
        $ids = is_array($messageIds) ? implode(',', $messageIds) : $messageIds;
        return $this->executeCommand("STORE $ids +FLAGS (\\Deleted)");
    }

    /**
     * Save attachment to file.
     * Set the path to the output directory by using method setAttachmentsDirectory
     *
     * @param int $messageId
     * @param string $filename
     * @return bool
     */
    public function saveAttachment(int $messageId, string $filename): bool
    {
        $attachments = $this->getAttachments($messageId);

        foreach ($attachments as $attachment) {
            if ($attachment['filename'] === $filename) {
                $fullPath = $this->attachmentsDirectory . DIRECTORY_SEPARATOR . $filename;
                return file_put_contents($fullPath, $attachment['content']) !== false;
            }
        }

        return false;
    }

    /**
     * Attribute to specify the directory where to save the attachments
     *
     * @param string $directory
     * @return bool
     */
    public function setAttachmentsDirectory(string $directory): bool
    {
        if (is_dir($directory) && is_writable($directory)) {
            $this->attachmentsDirectory = $directory;
        }
        return false;
    }

    /**
     * Close connection
     *
     * @return void
     */
    public function close()
    {
        if ($this->socket) {
            $this->executeCommand("LOGOUT");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Get and decode text parts: plain and html
     *
     * @param int|string $messageId
     * @return array ['plain' => string, 'html' => string]
     */
    public function getMessageText($messageId): array
    {
        // get "raw" content
        $raw = $this->executeCommand("FETCH $messageId (BODY.PEEK[])");
        if (!preg_match("/\r\n\r\n/", $raw)) {
            return ['plain' => '', 'html' => ''];
        }
        list($rawHeaders, $rawBody) = preg_split("/\r\n\r\n/", $raw, 2);

        // search for boundary
        $boundary = null;
        if (preg_match('/boundary="?([^";]+)"?/i', $rawHeaders, $bm)) {
            $boundary = trim($bm[1]);
        }

        $parts = [];
        if ($boundary) {
            $blocks = preg_split('/--' . preg_quote($boundary, '/') . '/', $rawBody);
            foreach ($blocks as $block) {
                $block = trim($block);
                if (!$block || $block === '--') continue;
                if (!preg_match("/\r\n\r\n/", $block)) continue;
                list($hdr, $body) = preg_split("/\r\n\r\n/", $block, 2);
                $parts[] = ['headers' => $hdr, 'body' => $body];
            }
        } else {
            $parts[] = ['headers' => $rawHeaders, 'body' => $rawBody];
        }

        $plain = '';
        $html = '';
        foreach ($parts as $part) {
            $ct = '';
            if (preg_match('/Content-Type:\s*([^;]+)/i', $part['headers'], $m)) {
                $ct = strtolower(trim($m[1]));
            }
            $decoded = $this->decodeBody($part['headers'], $part['body']);
            if (strpos($ct, 'text/plain') !== false) {
                $plain .= $decoded;
            }
            if (strpos($ct, 'text/html') !== false) {
                $html .= $decoded;
            }
        }

        return ['plain' => $plain, 'html' => $html];
    }

    /**
     * Decode body based on Content-Transfer-Encoding
     *
     * @param $hdr
     * @param $body
     * @return false|mixed|string
     */
    private function decodeBody($hdr, $body)
    {
        $encoding = null;
        if (preg_match('/Content-Transfer-Encoding:\s*([^\r\n]+)/i', $hdr, $em)) {
            $encoding = strtolower(trim($em[1]));
        }
        switch ($encoding) {
            case 'base64':
                return base64_decode($body);
            case 'quoted-printable':
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * Get header fields (From, To, Date etc.)
     *
     * @param int|string $messageId
     * @param array $fields
     * @return array
     */
    public function getHeaderFields($messageId, array $fields): array
    {
        $list = implode(' ', $fields);
        $raw = $this->executeCommand("FETCH $messageId (BODY.PEEK[HEADER.FIELDS ($list)])");
        $result = [];
        $lines = preg_split("/\r\n|\n/", $raw);
        foreach ($lines as $line) {
            foreach ($fields as $field) {
                if (stripos($line, $field . ':') === 0) {
                    $value = trim(substr($line, strlen($field) + 1));
                    $result[strtoupper($field)][] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Get From field
     *
     * @param int|string $messageId
     * @return string
     */
    public function getFrom($messageId): string
    {
        $h = $this->getHeaderFields($messageId, ['FROM']);
        return $h['FROM'] ?? '';
    }

    /**
     * Get To field
     *
     * @param int|string $messageId
     * @return array
     */
    public function getTo($messageId): array
    {
        $h = $this->getHeaderFields($messageId, ['TO']);
        return $h['TO'] ?? [];
    }

    /**
     * Get Date field
     *
     * @param int|string $messageId
     * @return string
     */
    public function getDate($messageId): string
    {
        $h = $this->getHeaderFields($messageId, ['DATE']);
        return $h['DATE'][0] ?? '';
    }

    /**
     * @param int|string $messageId
     * @return array [['filename' => string, 'data' => binary, 'content_type' => string], ...]
     */
    public function getAttachments($messageId): array
    {
        $raw = $this->executeCommand("FETCH $messageId (BODY.PEEK[])");
        if (!preg_match("/\r\n\r\n/", $raw)) {
            return [];
        }
        list($rawHeaders, $rawBody) = preg_split("/\r\n\r\n/", $raw, 2);
        $boundary = null;
        if (preg_match('/boundary="?([^";]+)"?/i', $rawHeaders, $bm)) {
            $boundary = $bm[1];
        }
        $attachments = [];
        if ($boundary) {
            $parts = preg_split('/--' . preg_quote($boundary, '/') . '/', $rawBody);
            foreach ($parts as $part) {
                if (stripos($part, 'Content-Disposition: attachment') !== false) {
                    list($pHdr, $pBody) = preg_split("/\r\n\r\n/", trim($part), 2);
                    $filename = 'unknown';
                    if (preg_match('/filename="([^"]+)"/i', $pHdr, $fm)) {
                        $filename = $fm[1];
                    }
                    $ctype = 'application/octet-stream';
                    if (preg_match('/Content-Type:\s*([^;\r\n]+)/i', $pHdr, $cm)) {
                        $ctype = trim($cm[1]);
                    }
                    $data = $this->decodeBody($pHdr, trim($pBody));
                    $attachments[] = [
                        'filename' => $filename,
                        'data' => $data,
                        'content_type' => $ctype,
                    ];
                }
            }
        }
        return $attachments;
    }
}