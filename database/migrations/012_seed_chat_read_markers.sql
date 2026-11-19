-- Existing conversations are history, not a flood of newly unread messages.
-- Start current participants at the latest message that existed at rollout.
INSERT INTO chat_reads (request_id, user_id, last_read_message_id)
SELECT participants.request_id, participants.user_id, MAX(messages.id)
FROM (
    SELECT id AS request_id, customer_id AS user_id
    FROM repair_requests
    UNION ALL
    SELECT id AS request_id, technician_id AS user_id
    FROM repair_requests
    WHERE technician_id IS NOT NULL
    UNION ALL
    SELECT requests.id AS request_id, users.id AS user_id
    FROM repair_requests AS requests
    CROSS JOIN users
    WHERE users.role = 'admin' AND users.is_active = 1
) AS participants
INNER JOIN request_messages AS messages ON messages.request_id = participants.request_id
GROUP BY participants.request_id, participants.user_id;
