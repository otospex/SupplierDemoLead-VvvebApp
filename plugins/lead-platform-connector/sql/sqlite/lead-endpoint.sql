-- Lead endpoints

	-- Get one
	CREATE PROCEDURE get(
		IN lead_endpoint_id INT,
		OUT fetch_row,
	)
	BEGIN
		SELECT *
			FROM lead_endpoint as _
		WHERE lead_endpoint_id = :lead_endpoint_id LIMIT 1;
	END

	-- Edit
	CREATE PROCEDURE edit(
		IN lead_endpoint ARRAY,
		IN lead_endpoint_id INT,
		OUT affected_rows
	)
	BEGIN
		@FILTER(:lead_endpoint, lead_endpoint)
		UPDATE lead_endpoint
			SET @LIST(:lead_endpoint)
		WHERE lead_endpoint_id = :lead_endpoint_id
	END

	-- Add
	CREATE PROCEDURE add(
		IN lead_endpoint ARRAY,
		OUT insert_id
	)
	BEGIN
		:lead_endpoint = @FILTER(:lead_endpoint, lead_endpoint)
		INSERT INTO lead_endpoint
			( @KEYS(:lead_endpoint) )
			VALUES ( :lead_endpoint );
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
			FROM lead_endpoint
		ORDER BY active DESC, lead_endpoint_id DESC
		LIMIT :limit OFFSET :start;

		SELECT count(*) FROM (
			@SQL_COUNT(lead_endpoint.lead_endpoint_id, lead_endpoint)
		) as count;
	END

	-- Delete
	CREATE PROCEDURE delete(
		IN  lead_endpoint_id ARRAY,
		OUT affected_rows
	)
	BEGIN
		DELETE FROM lead_endpoint WHERE lead_endpoint_id IN (:lead_endpoint_id)
	END
