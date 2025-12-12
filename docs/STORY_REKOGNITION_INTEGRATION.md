# Story Rekognition Integration

## Overview
Stories now use the same AWS Rekognition system as Reels. Both share the same `reels_aws_upload` table for storing AWS analysis data.

## Database Changes Required

### Add columns to `users_story` table:
```sql
ALTER TABLE users_story 
ADD COLUMN IF NOT EXISTS rekognition TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS story_src_hls TEXT DEFAULT NULL;
```

**Note**: The `reels_aws_upload` table is already in use for Reels and will be shared for Stories. No new table needed.

## How It Works

### 1. File Upload Flow
```
User uploads story → S3 → AWS Lambda → Rekognition Analysis
                                     ↓
                              Webhook saves to reels_aws_upload
                                     ↓
                              belloo.php polls table
                                     ↓
                              Sets story visibility status
```

### 2. Visibility Logic (Same as Reels)

```php
$maxScore = max($nudity, $sexual, $violence, $other);

if ($maxScore >= 80) {
    $visible = 2; // Rejected - violates policy
} else if ($maxScore >= 60) {
    $visible = 0; // Pending - needs manual review
} else {
    $visible = 1; // Approved - safe content
}
```

### 3. User Feedback

- **Approved**: "Story uploaded successfully!" ✅
- **Pending**: "Your story is pending review and will be visible after moderation." ⏳
- **Rejected**: "Your story was rejected due to content policy violations." ❌

## Files Modified

1. **`requests/belloo.php`** - `uploadStory` case
   - Polls `reels_aws_upload` table (shared with reels)
   - Determines visibility based on moderation scores
   - Saves rekognition data with story

2. **`mobile-react/src/pages/Stories.tsx`** - `saveStory` function
   - Handles different status responses
   - Shows appropriate notifications
   - Reloads stories only if not rejected

## Testing

1. **Add database columns** (run SQL above)
2. **Upload a story** with appropriate content → Should auto-approve
3. **Upload a story** with inappropriate content → Should reject or pend
4. **Check notifications** for each status type
5. **Verify admin panel** can manually approve/reject pending stories

## AWS Lambda Integration

Your AWS Lambda should send webhooks to:
```
POST /requests/aws-lambda-webhook.php
```

With payload:
```json
{
  "file_name": "filename.jpg",
  "hls_url": "https://...",
  "moderation_scores": {
    "nudity": 5.2,
    "sexual": 3.1,
    "violence": 0.5,
    "other": 1.2
  }
}
```

The webhook handler will save this data to `reels_aws_upload` table, which is then used by both Reels and Stories.

## Summary

✅ Stories now have automatic content moderation  
✅ Shares infrastructure with Reels (same table, same Lambda)  
✅ Three visibility states: Approved (1), Pending (0), Rejected (2)  
✅ Frontend shows status-specific notifications  
✅ Admin can manually approve/reject pending content  

