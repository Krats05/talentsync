<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Summarizer</title>
</head>
<body>

    <h2>Interview Feedback Summarizer Test</h2>

    <form method="POST" action="api/summarize_feedback.php">
        <textarea name="feedback" rows="8" cols="60" placeholder="Write interview feedback here..."></textarea>
        <br><br>
        <button type="submit">Summarize</button>
    </form>

</body>
</html>