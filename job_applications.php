<form action="api/update_application_status.php" method="POST">
<input type="hidden" name="application_id" value="<?= $app['id'] ?>">

<select name="status">
<option value="Applied">Applied</option>
<option value="Under Review">Under Review</option>
<option value="Interview Scheduled">Interview Scheduled</option>
<option value="Offer">Offer</option>
<option value="Rejected">Rejected</option>
</select>

<button type="submit">Update</button>
</form>