<?php
/**
 * MailHandler Class
 * Handles integration with Gmail API and IMAP for email scanning and actions.
 */
class MailHandler {
    private $client;
    private $service;
    private $userEmail;

    public function __construct($accessToken = null) {
        // Initialize Google Client if accessToken is provided
        $this->client = new Google_Client();
        $this->client->setClientId(GOOGLE_CLIENT_ID);
        $this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
        if ($accessToken) {
            $this->client->setAccessToken($accessToken);
            $this->service = new Google_Service_Gmail($this->client);
        }
        $this->userEmail = 'me'; // Special keyword in Gmail API for the authenticated user
    }

    /**
     * Gets all message IDs matching a query (minimal data transfer)
     */
    public function getAllMessageIds($query) {
        $ids = [];
        if (!$this->service) return $ids;

        try {
            $pageToken = NULL;
            do {
                $optParams = [
                    'q' => $query,
                    'pageToken' => $pageToken,
                    'fields' => 'messages(id),nextPageToken',
                    'maxResults' => 500 // Get many IDs at once
                ];
                $results = $this->service->users_messages->listUsersMessages($this->userEmail, $optParams);
                
                if ($results->getMessages()) {
                    foreach ($results->getMessages() as $message) {
                        $ids[] = $message->getId();
                    }
                }
                $pageToken = $results->getNextPageToken();
                
                // Safety break (max 5000 IDs for sync)
                if (count($ids) >= 5000) break;

            } while ($pageToken);
        } catch (Exception $e) {
            error_log("Error fetching message IDs: " . $e->getMessage());
        }
        return $ids;
    }

    /**
     * Scans the inbox for a specific query
     */
    public function scanInbox($query = 'category:promotions OR category:social', $limit = 100, $callback = null) {
        $messages = [];
        if (!$this->service) return $messages;

        try {
            $pageToken = NULL;
            do {
                $batchSize = ($limit > 0 && ($limit - count($messages)) < 100) ? ($limit - count($messages)) : 100;
                if ($batchSize <= 0 && $limit > 0) break;

                $optParams = [
                    'maxResults' => $batchSize,
                    'q' => $query,
                    'pageToken' => $pageToken
                ];
                $results = $this->service->users_messages->listUsersMessages($this->userEmail, $optParams);
                
                if ($results->getMessages()) {
                    foreach ($results->getMessages() as $message) {
                        // Fetch full message details
                        $msg = $this->service->users_messages->get($this->userEmail, $message->getId());
                        $messages[] = $this->parseMessage($msg);
                        
                        // Call progress callback
                        if ($callback) call_user_func($callback, count($messages));
                        
                        // Break if we reached the user's limit
                        if ($limit > 0 && count($messages) >= $limit) break 2;
                    }
                }
                $pageToken = $results->getNextPageToken();
                
                // Safety break to prevent infinite loop or extreme timeouts (max 2000 total for safety)
                if (count($messages) >= 2000) break; 
                
            } while ($pageToken);

        } catch (Exception $e) {
            error_log("Error scanning inbox: " . $e->getMessage());
        }
        return $messages;
    }

    /**
     * Safely trashes emails (moves to trash, does not permanently delete)
     */
    public function trashEmails($messageIds) {
        if (!$this->service) return 0;
        $successCount = 0;
        try {
            foreach($messageIds as $id) {
                $this->service->users_messages->trash($this->userEmail, $id);
                $successCount++;
            }
        } catch(Exception $e) {
            error_log("Error trashing emails: " . $e->getMessage());
        }
        return $successCount;
    }

    /**
     * Permanently deletes emails (cannot be recovered from trash)
     */
    public function permanentDelete($messageIds) {
        if (!$this->service) return 0;
        $successCount = 0;
        try {
            foreach($messageIds as $id) {
                $this->service->users_messages->delete($this->userEmail, $id);
                $successCount++;
            }
        } catch(Exception $e) {
            error_log("Error deleting emails permanently: " . $e->getMessage());
        }
        return $successCount;
    }

    /**
     * Identifies unsubscribe links in emails
     */
    public function findUnsubscribeLinks($messageId) {
        if (!$this->service) return null;
        try {
            $msg = $this->service->users_messages->get($this->userEmail, $messageId, ['format' => 'metadata', 'metadataHeaders' => ['List-Unsubscribe']]);
            $headers = $msg->getPayload()->getHeaders();
            foreach($headers as $header) {
                if($header->getName() === 'List-Unsubscribe') {
                    return $header->getValue();
                }
            }
        } catch (Exception $e) {
            error_log("Error finding unsubscribe links: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Parses the Google Service Message object into our app format
     */
    private function parseMessage($googleMessage) {
        $parsed = [
            'id' => $googleMessage->getId(),
            'snippet' => $googleMessage->getSnippet(),
            'size' => $googleMessage->getSizeEstimate(),
            'labels' => $googleMessage->getLabelIds(),
            'is_unread' => in_array('UNREAD', $googleMessage->getLabelIds()),
            'unsubscribe_url' => null
        ];
        
        $headers = $googleMessage->getPayload()->getHeaders();
        foreach ($headers as $header) {
            if ($header->getName() == 'Subject') {
                $parsed['subject'] = $header->getValue();
            }
            if ($header->getName() == 'From') {
                $parsed['sender'] = $header->getValue();
            }
            if ($header->getName() == 'Date') {
                $parsed['date'] = $header->getValue();
            }
            if ($header->getName() == 'List-Unsubscribe') {
                // Extract URL from <url> format
                if(preg_match('/<(https?:\/\/[^>]+)>/', $header->getValue(), $matches)) {
                    $parsed['unsubscribe_url'] = $matches[1];
                }
            }
        }
        
        return $parsed;
    }
}
?>
