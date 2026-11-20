-- 014: a chat message may contain only images, with no text body.
ALTER TABLE request_messages MODIFY body TEXT NULL;
