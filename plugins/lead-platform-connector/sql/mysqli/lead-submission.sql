-- Lead submissions (audit log)

	-- Get one
	CREATE PROCEDURE get(
		IN lead_submission_id INT,
		OUT fetch_row,
	)
	BEGIN
		SELECT *
			FROM lead_submission as _
		WHERE lead_submission_id = :lead_submission_id LIMIT 1;
	END

	-- Add (used by submit proxy)
	CREATE PROCEDURE add(
		IN lead_submission ARRAY,
		OUT insert_id
	)
	BEGIN
		:lead_submission = @FILTER(:lead_submission, lead_submission)
		INSERT INTO lead_submission
			( @KEYS(:lead_submission) )
			VALUES ( :lead_submission );
	END

	-- Edit (used to update retry status)
	CREATE PROCEDURE edit(
		IN lead_submission ARRAY,
		IN lead_submission_id INT,
		OUT affected_rows
	)
	BEGIN
		@FILTER(:lead_submission, lead_submission)
		UPDATE lead_submission
			SET @LIST(:lead_submission)
		WHERE lead_submission_id = :lead_submission_id
	END

	-- List
	CREATE PROCEDURE getAll(
		IN  language_id INT,
		IN  user_group_id INT,
		IN  site_id INT,
		IN  search CHAR,
		IN  start INT,
		IN  limit INT,
		OUT fetch_all,
		OUT fetch_one,
	)
	BEGIN
		SELECT *
			FROM lead_submission
		ORDER BY lead_submission_id DESC
		LIMIT :limit OFFSET :start;

		SELECT count(*) FROM (
			@SQL_COUNT(lead_submission.lead_submission_id, lead_submission)
		) as count;
	END

	-- Delete
	CREATE PROCEDURE delete(
		IN  lead_submission_id ARRAY,
		OUT affected_rows
	)
	BEGIN
		DELETE FROM lead_submission WHERE lead_submission_id IN (:lead_submission_id)
	END
