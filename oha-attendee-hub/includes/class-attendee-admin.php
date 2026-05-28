<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Attendee_Admin {

	// =========================================================================
	// All Attendees list page
	// =========================================================================
	public static function page_list() : void {
		$default_year  = (int) get_option( 'oha_attendee_default_year', date( 'Y' ) );
		$year          = isset( $_GET['year'] )         ? (int) sanitize_text_field( $_GET['year'] )             : $default_year;
		$search        = isset( $_GET['s'] )            ? sanitize_text_field( wp_unslash( $_GET['s'] ) )        : '';
		$filter_ticket = isset( $_GET['ticket_level'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket_level'] ) ) : '';
		$filter_addon  = isset( $_GET['addon'] )        ? sanitize_text_field( wp_unslash( $_GET['addon'] ) )    : '';
		$paged         = isset( $_GET['paged'] )        ? max( 1, (int) $_GET['paged'] )                        : 1;
		$limit         = 50;
		$offset        = ( $paged - 1 ) * $limit;
		$years         = OHA_Attendee_DB::get_years();
		$ticket_levels = OHA_Attendee_DB::get_distinct_ticket_levels( $year );
		$addons        = OHA_Attendee_DB::get_distinct_addons( $year );

		$args  = [ 'year' => $year, 'search' => $search, 'ticket_level' => $filter_ticket, 'addon' => $filter_addon, 'limit' => $limit, 'offset' => $offset ];
		$rows  = OHA_Attendee_DB::get_attendees( $args );
		$total = OHA_Attendee_DB::count_attendees( $args );
		$pages = (int) ceil( $total / $limit );
		?>
		<div class="wrap oha-att-wrap">
			<h1 class="wp-heading-inline">Convention Attendees</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oha-attendee-import' ) ); ?>" class="page-title-action">Import CSV</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oha-attendee-welcome' ) ); ?>" class="page-title-action">Welcome Email</a>
			<hr class="wp-header-end">

			<form method="get" class="oha-att-filter-bar">
				<input type="hidden" name="page" value="oha-attendee-hub">
				<?php if ( $years ) : ?>
				<select name="year" onchange="this.form.submit()">
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo (int) $y; ?>" <?php selected( $y, $year ); ?>><?php echo (int) $y; ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>

				<?php if ( $ticket_levels ) : ?>
				<select name="ticket_level">
					<option value="">All ticket types</option>
					<?php foreach ( $ticket_levels as $tl ) : ?>
						<option value="<?php echo esc_attr( $tl ); ?>" <?php selected( $tl, $filter_ticket ); ?>><?php echo esc_html( $tl ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>

				<?php if ( $addons ) : ?>
				<select name="addon">
					<option value="">All add-ons</option>
					<?php foreach ( $addons as $a ) : ?>
						<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $a, $filter_addon ); ?>><?php echo esc_html( $a ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>

				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name or email">
				<button type="submit" class="button">Filter</button>
				<?php if ( $filter_ticket || $filter_addon || $search ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-attendee-hub', 'year' => $year ], admin_url( 'admin.php' ) ) ); ?>" class="button">Clear</a>
				<?php endif; ?>
				<span class="oha-att-count"><?php echo number_format( $total ); ?> attendee<?php echo $total !== 1 ? 's' : ''; ?></span>
			</form>

			<table class="wp-list-table widefat fixed striped oha-att-table">
				<thead>
					<tr>
						<th class="col-name">Name</th>
						<th class="col-email">Email</th>
						<th>Ticket Level</th>
						<th>Add-On</th>
						<th>Amount</th>
						<th>Phone</th>
						<th>WP Account</th>
						<th>Welcome</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8" style="text-align:center;padding:20px;">No attendees found<?php echo $year ? ' for ' . $year : ''; ?>.</td></tr>
				<?php else : foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( trim( $r->last_name . ', ' . $r->first_name, ', ' ) ); ?></td>
						<td><?php echo esc_html( $r->email ); ?></td>
						<td><?php echo esc_html( $r->ticket_level ); ?></td>
						<td><?php echo esc_html( $r->add_on_name ); ?></td>
						<td><?php echo esc_html( $r->purchase_amount ); ?></td>
						<td><?php echo esc_html( $r->phone ); ?></td>
						<td>
							<?php if ( $r->user_id ) : ?>
								<?php if ( (int) $r->wp_user_created === 1 ) : ?>
									<span class="oha-badge oha-badge-new">New</span>
								<?php else : ?>
									<span class="oha-badge oha-badge-existing">Existing</span>
								<?php endif; ?>
							<?php else : ?>
								<span class="oha-badge oha-badge-none">None</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( (int) $r->welcome_sent === 1 ) : ?>
								<span class="oha-badge oha-badge-sent">Sent</span>
							<?php else : ?>
								<span class="oha-badge oha-badge-pending">Pending</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( [
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					] );
					?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Import CSV page
	// =========================================================================
	public static function page_import() : void {
		$stage        = isset( $_GET['stage'] ) ? sanitize_key( $_GET['stage'] ) : 'upload';
		$year         = isset( $_GET['year'] )  ? (int) $_GET['year'] : (int) get_option( 'oha_attendee_default_year', date( 'Y' ) );
		$current_year = (int) date( 'Y' );
		?>
		<div class="wrap oha-att-wrap">
			<h1>Import Convention Attendees</h1>

			<?php if ( isset( $_GET['upload_error'] ) ) : ?>
				<div class="notice notice-error"><p>Upload failed. Make sure the file is a .csv and try again.</p></div>
			<?php endif; ?>

			<?php if ( $stage === 'upload' ) : ?>

				<p>Upload the attendee export CSV from your ticketing system. Column order must match:</p>
				<p class="oha-att-col-list">
					Date &bull; Event Name &bull; Last Name &bull; First Name &bull; Purchase Amount &bull; Ticket Level &bull;
					Email &bull; Add-On Name &bull; Phone &bull; Address 1 &bull; Address 2 &bull; City &bull; State &bull; Zip &bull; Country
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="oha-att-upload-form">
					<?php wp_nonce_field( 'oha_attendee_upload' ); ?>
					<input type="hidden" name="action" value="oha_attendee_upload">

					<table class="form-table">
						<tr>
							<th><label for="oha-year">Convention Year</label></th>
							<td>
								<select name="year" id="oha-year">
									<?php for ( $y = $current_year + 1; $y >= 2020; $y-- ) : ?>
										<option value="<?php echo $y; ?>" <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
									<?php endfor; ?>
								</select>
								<p class="description">Records will be tagged to this year.</p>
							</td>
						</tr>
						<tr>
							<th><label for="oha-csv">CSV File</label></th>
							<td><input type="file" name="csv_file" id="oha-csv" accept=".csv" required></td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">Upload and Preview</button>
					</p>
				</form>

			<?php elseif ( $stage === 'preview' ) : ?>

				<p>Review the import below, then click <strong>Confirm and Import</strong> to proceed. New WP accounts will be created for attendees without an existing account.</p>

				<div id="oha-preview-area"><p class="oha-att-loading">Loading preview...</p></div>

				<script>
				jQuery(function($){
					$.post(ohaAttendee.ajax_url, {
						action: 'oha_attendee_preview',
						nonce:  ohaAttendee.nonce,
						year:   <?php echo (int) $year; ?>
					}, function(res){
						if ( ! res.success ) {
							$('#oha-preview-area').html('<div class="notice notice-error"><p>' + escH(res.data) + '</p></div>');
							return;
						}
						var d = res.data;
						var mergeNote = (d.raw_rows && d.raw_rows !== d.total) ? '<br><small style="font-size:11px;font-weight:400">' + d.raw_rows + ' CSV rows</small>' : '';
						var html = '<div class="oha-att-stats">'
							+ '<div class="oha-att-stat"><span>' + d.total    + '</span>Attendees' + mergeNote + '</div>'
							+ '<div class="oha-att-stat oha-s-new"><span>' + d.new      + '</span>Creating new accounts</div>'
							+ '<div class="oha-att-stat oha-s-exist"><span>' + d.existing + '</span>Updating existing accounts</div>'
							+ (d.skipped ? '<div class="oha-att-stat oha-s-skip"><span>' + d.skipped + '</span>Skipped (no email)</div>' : '')
							+ '</div>';

						if ( d.preview.length ) {
							html += '<h3>First ' + d.preview.length + ' attendees:</h3>';
							html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Email</th><th>Ticket</th><th>Add-Ons</th><th>Status</th></tr></thead><tbody>';
							$.each(d.preview, function(i, r){
								html += '<tr><td>' + escH(r.last + ', ' + r.first) + '</td><td>' + escH(r.email) + '</td><td>' + escH(r.ticket) + '</td><td>' + escH(r.addons || '-') + '</td><td><span class="oha-badge oha-badge-' + r.status + '">' + r.status + '</span></td></tr>';
							});
							html += '</tbody></table>';
						}

						html += '<p class="oha-att-import-btns">'
							+ '<button id="oha-do-import" class="button button-primary">Confirm and Import ' + d.total + ' Attendees (Year: ' + d.year + ')</button>'
							+ ' <a href="<?php echo esc_url( admin_url( 'admin.php?page=oha-attendee-import' ) ); ?>" class="button">Cancel</a>'
							+ '</p>';
						html += '<div id="oha-import-msg"></div>';

						$('#oha-preview-area').html(html);

						$('#oha-do-import').on('click', function(){
							$(this).prop('disabled', true).text('Importing...');
							$.post(ohaAttendee.ajax_url, {
								action: 'oha_attendee_import',
								nonce:  ohaAttendee.nonce,
								year:   d.year
							}, function(r2){
								var msg;
								if ( ! r2.success ) {
									$('#oha-import-msg').html('<div class="notice notice-error"><p>' + escH(r2.data) + '</p></div>');
									$('#oha-do-import').prop('disabled', false).text('Retry');
									return;
								}
								var i = r2.data;
								msg = 'Import complete. <strong>' + i.created + '</strong> new accounts created, <strong>' + i.updated + '</strong> existing accounts updated';
								if ( i.skipped ) msg += ', <strong>' + i.skipped + '</strong> skipped';
								msg += '.';
								if ( i.errors && i.errors.length ) {
									msg += '<br><strong>Errors:</strong><br>' + i.errors.map(escH).join('<br>');
								}
								$('#oha-import-msg').html(
									'<div class="notice notice-success"><p>' + msg + '</p>'
									+ '<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=oha-attendee-hub' ) ); ?>">View Attendees</a> &bull; '
									+ '<a href="<?php echo esc_url( admin_url( 'admin.php?page=oha-attendee-welcome' ) ); ?>">Send Welcome Emails</a></p></div>'
								);
								$('#oha-do-import').hide();
							});
						});
					});

					function escH(s){ return $('<div>').text(String(s)).html(); }
				});
				</script>

			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Welcome Email page
	// =========================================================================
	public static function page_welcome() : void {
		$default_year = (int) get_option( 'oha_attendee_default_year', date( 'Y' ) );
		$year    = isset( $_GET['year'] ) ? (int) $_GET['year'] : $default_year;
		$years   = OHA_Attendee_DB::get_years();
		$subject = OHA_Attendee_Mailer::get_subject();
		$body    = OHA_Attendee_Mailer::get_body();
		$unsent  = $year ? OHA_Attendee_DB::count_unsent( $year ) : 0;
		?>
		<div class="wrap oha-att-wrap">
			<h1>Welcome Email</h1>
			<p>Edit the template, preview it against a real attendee record, then send to all attendees who have not yet received a welcome email. Emails are sent in batches of 25 so the page will not time out on large lists.</p>

			<p class="oha-att-tag-list">
				Available merge tags:
				<code>{{first_name}}</code> <code>{{last_name}}</code> <code>{{email}}</code>
				<code>{{username}}</code> <code>{{year}}</code> <code>{{ticket_level}}</code>
				<code>{{add_ons}}</code> <code>{{purchase_amount}}</code>
				<code>{{set_password_link}}</code> <code>{{site_name}}</code> <code>{{site_url}}</code>
			</p>
			<p class="description"><code>{{set_password_link}}</code> outputs a password-set URL only for newly created accounts; it is blank for existing users.</p>

			<table class="form-table oha-att-welcome-form">
				<tr>
					<th><label for="oha-wel-year">Send to (year)</label></th>
					<td>
						<?php if ( $years ) : ?>
						<select id="oha-wel-year">
							<?php foreach ( $years as $y ) : ?>
								<option value="<?php echo (int) $y; ?>" <?php selected( $y, $year ); ?>><?php echo (int) $y; ?></option>
							<?php endforeach; ?>
						</select>
						<?php else : ?>
							<em>No attendees imported yet.</em>
						<?php endif; ?>
						<span id="oha-unsent-label" class="oha-att-unsent"><?php echo number_format( $unsent ); ?> not yet sent</span>
					</td>
				</tr>
				<tr>
					<th><label for="oha-wel-from-name">From Name</label></th>
					<td><input type="text" id="oha-wel-from-name" value="<?php echo esc_attr( OHA_Attendee_Mailer::get_from_name() ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="oha-wel-from-email">From Email</label></th>
					<td><input type="email" id="oha-wel-from-email" value="<?php echo esc_attr( OHA_Attendee_Mailer::get_from_email() ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="oha-wel-subject">Subject</label></th>
					<td><input type="text" id="oha-wel-subject" value="<?php echo esc_attr( $subject ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="oha-wel-body">Body</label></th>
					<td>
						<textarea id="oha-wel-body" rows="18" class="large-text"><?php echo esc_textarea( $body ); ?></textarea>
					</td>
				</tr>
			</table>

			<p class="oha-att-actions">
				<button id="oha-btn-preview" class="button">Preview Email</button>
				<button id="oha-btn-save"    class="button">Save Template</button>
				<button id="oha-btn-send"    class="button button-primary">
					Send Welcome Emails (<span id="oha-send-count"><?php echo number_format( $unsent ); ?></span> pending)
				</button>
			</p>

			<div id="oha-wel-status"></div>

			<div id="oha-preview-box" style="display:none">
				<h3>Rendered Preview</h3>
				<div class="oha-preview-subject">Subject: <strong id="oha-prev-subject"></strong></div>
				<pre id="oha-prev-body" class="oha-preview-body"></pre>
			</div>
		</div>

		<script>
		jQuery(function($){

			function year()       { return $('#oha-wel-year').val(); }
			function subject()    { return $('#oha-wel-subject').val(); }
			function body()       { return $('#oha-wel-body').val(); }
			function fromName()   { return $('#oha-wel-from-name').val(); }
			function fromEmail()  { return $('#oha-wel-from-email').val(); }

			$('#oha-wel-year').on('change', function(){
				$.post(ohaAttendee.ajax_url, { action:'oha_attendee_unsent_count', nonce:ohaAttendee.nonce, year:year() }, function(r){
					if(r.success){
						var c = r.data.count;
						$('#oha-unsent-label').text(c + ' not yet sent');
						$('#oha-send-count').text(c);
					}
				});
			});

			$('#oha-btn-preview').on('click', function(){
				$.post(ohaAttendee.ajax_url, {
					action:'oha_attendee_preview_email', nonce:ohaAttendee.nonce,
					year:year(), subject:subject(), body:body()
				}, function(r){
					if(!r.success){ alert(r.data); return; }
					$('#oha-prev-subject').text(r.data.subject);
					$('#oha-prev-body').text(r.data.body);
					$('#oha-preview-box').show();
					$('#oha-preview-box')[0].scrollIntoView({behavior:'smooth'});
				});
			});

			$('#oha-btn-save').on('click', function(){
				var $btn = $(this).prop('disabled',true).text('Saving...');
				$.post(ohaAttendee.ajax_url, {
					action:'oha_attendee_save_template', nonce:ohaAttendee.nonce,
					subject:subject(), body:body(), from_name:fromName(), from_email:fromEmail()
				}, function(r){
					$btn.prop('disabled',false).text('Save Template');
					if(r.success){
						showStatus('Template saved.', 'success');
					} else {
						showStatus('Save failed. Please try again.', 'error');
					}
				});
			});

			var sending = false;
			$('#oha-btn-send').on('click', function(){
				if(sending) return;
				var pending = parseInt($('#oha-send-count').text().replace(/[^0-9]/g,''), 10);
				if(pending === 0){ alert('No pending welcome emails for the selected year.'); return; }
				if(!confirm('Send welcome emails to ' + pending + ' attendees from ' + year() + '?\n\nEmails will be sent in batches. Do not close this page until complete.')) return;

				sending = true;
				var totalSent = 0;
				showStatus('Sending... <span id="oha-sent-tally">0</span> sent so far.', 'info');

				function sendBatch(){
					$.post(ohaAttendee.ajax_url, {
						action:'oha_attendee_send_welcome', nonce:ohaAttendee.nonce,
						year:year(), subject:subject(), body:body(),
						from_name:fromName(), from_email:fromEmail(), batch:25
					}, function(r){
						if(!r.success){
							sending = false;
							showStatus('Error during send: ' + r.data, 'error');
							return;
						}
						var d = r.data;
						totalSent += d.sent;
						$('#oha-sent-tally').text(totalSent);
						$('#oha-send-count').text(d.remaining);
						$('#oha-unsent-label').text(d.remaining + ' not yet sent');

						if(d.remaining > 0){
							setTimeout(sendBatch, 600);
						} else {
							sending = false;
							showStatus('Done. ' + totalSent + ' welcome email' + (totalSent !== 1 ? 's' : '') + ' sent.', 'success');
						}
					}).fail(function(){
						sending = false;
						showStatus('Request failed. Reload and try again.', 'error');
					});
				}
				sendBatch();
			});

			function showStatus(msg, type){
				$('#oha-wel-status').html('<div class="notice notice-' + type + ' inline" style="margin:12px 0"><p>' + msg + '</p></div>');
			}
		});
		</script>
		<?php
	}

	// =========================================================================
	// Stats page
	// =========================================================================
	public static function page_stats() : void {
		$default_year = (int) get_option( 'oha_attendee_default_year', date( 'Y' ) );
		$year         = isset( $_GET['year'] ) ? (int) $_GET['year'] : $default_year;
		$years        = OHA_Attendee_DB::get_years();
		$rows         = $year ? OHA_Attendee_DB::get_all_for_stats( $year ) : [];

		$total         = count( $rows );
		$total_revenue = 0.0;
		$new_accounts  = 0;
		$ticket_counts = [];
		$ticket_revenue = [];
		$addon_counts  = [];
		$state_counts  = [];
		$welcome_sent  = 0;

		foreach ( $rows as $r ) {
			$amount         = (float) preg_replace( '/[^0-9.]/', '', $r->purchase_amount );
			$total_revenue += $amount;

			if ( $r->ticket_level ) {
				$ticket_counts[ $r->ticket_level ]  = ( $ticket_counts[ $r->ticket_level ]  ?? 0 ) + 1;
				$ticket_revenue[ $r->ticket_level ] = ( $ticket_revenue[ $r->ticket_level ] ?? 0.0 ) + $amount;
			}

			if ( $r->add_on_name ) {
				foreach ( array_map( 'trim', explode( ',', $r->add_on_name ) ) as $a ) {
					if ( $a ) $addon_counts[ $a ] = ( $addon_counts[ $a ] ?? 0 ) + 1;
				}
			}

			if ( $r->state ) {
				$state_counts[ $r->state ] = ( $state_counts[ $r->state ] ?? 0 ) + 1;
			}

			if ( (int) $r->wp_user_created === 1 ) $new_accounts++;
			if ( (int) $r->welcome_sent    === 1 ) $welcome_sent++;
		}

		arsort( $ticket_counts );
		arsort( $addon_counts );
		arsort( $state_counts );
		$existing_accounts = $total - $new_accounts;

		// Use granular addon stats from the addons table if available (v1.1.0+ imports)
		$addon_stats = $year ? OHA_Attendee_DB::get_addon_stats( $year ) : [];
		?>
		<div class="wrap oha-att-wrap">
			<h1>Convention Stats</h1>

			<form method="get" style="margin-bottom:20px">
				<input type="hidden" name="page" value="oha-attendee-stats">
				<select name="year" onchange="this.form.submit()">
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo (int) $y; ?>" <?php selected( $y, $year ); ?>><?php echo (int) $y; ?></option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( ! $total ) : ?>
				<p>No attendees found for <?php echo $year; ?>.</p>
				<?php return; ?>
			<?php endif; ?>

			<!-- Summary cards -->
			<div class="oha-stats-cards">
				<div class="oha-stats-card">
					<div class="oha-stats-card-value"><?php echo number_format( $total ); ?></div>
					<div class="oha-stats-card-label">Total Attendees</div>
				</div>
				<div class="oha-stats-card">
					<div class="oha-stats-card-value">$<?php echo number_format( $total_revenue, 2 ); ?></div>
					<div class="oha-stats-card-label">Total Revenue</div>
				</div>
				<div class="oha-stats-card">
					<div class="oha-stats-card-value"><?php echo number_format( $new_accounts ); ?></div>
					<div class="oha-stats-card-label">New Accounts Created</div>
				</div>
				<div class="oha-stats-card">
					<div class="oha-stats-card-value"><?php echo number_format( $existing_accounts ); ?></div>
					<div class="oha-stats-card-label">Matched Existing Accounts</div>
				</div>
				<div class="oha-stats-card">
					<div class="oha-stats-card-value"><?php echo number_format( $welcome_sent ); ?></div>
					<div class="oha-stats-card-label">Welcome Emails Sent</div>
				</div>
				<div class="oha-stats-card">
					<div class="oha-stats-card-value"><?php echo count( $state_counts ); ?></div>
					<div class="oha-stats-card-label">States Represented</div>
				</div>
			</div>

			<div class="oha-stats-cols">

				<!-- Ticket levels -->
				<div class="oha-stats-section">
					<h2>Ticket Types</h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Ticket Level</th><th style="text-align:right">Count</th><th style="text-align:right">Revenue</th><th style="text-align:right">% of Total</th></tr></thead>
						<tbody>
						<?php foreach ( $ticket_counts as $label => $count ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-attendee-hub', 'year' => $year, 'ticket_level' => $label ], admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $label ); ?>
									</a>
								</td>
								<td style="text-align:right"><?php echo number_format( $count ); ?></td>
								<td style="text-align:right">$<?php echo number_format( $ticket_revenue[ $label ] ?? 0, 2 ); ?></td>
								<td style="text-align:right"><?php echo $total ? round( $count / $total * 100, 1 ) : 0; ?>%</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Add-ons: use granular addons table if available, else fall back to comma-sep counts -->
				<?php if ( $addon_stats ) : ?>
				<div class="oha-stats-section">
					<h2>Add-Ons</h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Add-On</th><th style="text-align:right">Count</th><th style="text-align:right">Revenue</th><th style="text-align:right">% of Attendees</th></tr></thead>
						<tbody>
						<?php foreach ( $addon_stats as $label => $stat ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-attendee-roster', 'year' => $year, 'addon' => $label ], admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $label ); ?>
									</a>
								</td>
								<td style="text-align:right"><?php echo number_format( $stat['count'] ); ?></td>
								<td style="text-align:right">$<?php echo number_format( $stat['total'], 2 ); ?></td>
								<td style="text-align:right"><?php echo $total ? round( $stat['count'] / $total * 100, 1 ) : 0; ?>%</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description" style="margin-top:6px">
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-attendee-roster', 'year' => $year ], admin_url( 'admin.php' ) ) ); ?>">View full class rosters</a>
					</p>
				</div>
				<?php elseif ( $addon_counts ) : ?>
				<div class="oha-stats-section">
					<h2>Add-Ons</h2>
					<p class="description">Re-import this year's CSV to see per-add-on revenue and enable the Roster page.</p>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Add-On</th><th style="text-align:right">Count</th><th style="text-align:right">% of Attendees</th></tr></thead>
						<tbody>
						<?php foreach ( $addon_counts as $label => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td style="text-align:right"><?php echo number_format( $count ); ?></td>
								<td style="text-align:right"><?php echo $total ? round( $count / $total * 100, 1 ) : 0; ?>%</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<!-- States -->
				<?php if ( $state_counts ) : ?>
				<div class="oha-stats-section">
					<h2>Attendees by State</h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>State</th><th style="text-align:right">Count</th><th style="text-align:right">% of Total</th></tr></thead>
						<tbody>
						<?php foreach ( $state_counts as $state => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $state ); ?></td>
								<td style="text-align:right"><?php echo number_format( $count ); ?></td>
								<td style="text-align:right"><?php echo $total ? round( $count / $total * 100, 1 ) : 0; ?>%</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

			</div><!-- .oha-stats-cols -->
		</div>
		<?php
	}

	// =========================================================================
	// Roster page — class/add-on attendance list for instructors
	// =========================================================================
	public static function page_roster() : void {
		$default_year = (int) get_option( 'oha_attendee_default_year', date( 'Y' ) );
		$year         = isset( $_GET['year'] )  ? (int) $_GET['year']                                        : $default_year;
		$filter_addon = isset( $_GET['addon'] ) ? sanitize_text_field( wp_unslash( $_GET['addon'] ) )        : '';
		$years        = OHA_Attendee_DB::get_years();
		$addons       = OHA_Attendee_DB::get_distinct_addons( $year );
		$roster       = [];

		if ( $filter_addon ) {
			$roster = OHA_Attendee_DB::get_roster_by_addon( $filter_addon, $year );
		}
		?>
		<div class="wrap oha-att-wrap">
			<h1>Add-On Roster</h1>
			<p>Select a year and add-on to see the list of attendees who signed up. Use this to give instructors a class attendance list.</p>

			<form method="get" class="oha-att-filter-bar">
				<input type="hidden" name="page" value="oha-attendee-roster">

				<?php if ( $years ) : ?>
				<select name="year" onchange="this.form.submit()">
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo (int) $y; ?>" <?php selected( $y, $year ); ?>><?php echo (int) $y; ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>

				<?php if ( $addons ) : ?>
				<select name="addon">
					<option value="">-- Select an add-on --</option>
					<?php foreach ( $addons as $a ) : ?>
						<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $a, $filter_addon ); ?>><?php echo esc_html( $a ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary">View Roster</button>
				<?php else : ?>
				<em>No add-on data found for <?php echo $year; ?>. Import a CSV to populate rosters.</em>
				<?php endif; ?>

				<?php if ( $filter_addon ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-attendee-roster', 'year' => $year ], admin_url( 'admin.php' ) ) ); ?>" class="button">Clear</a>
				<?php endif; ?>
			</form>

			<?php if ( $filter_addon ) : ?>

				<h2 style="margin-top:16px">
					<?php echo esc_html( $filter_addon ); ?>
					<span style="font-size:14px;font-weight:400;color:#666;margin-left:8px"><?php echo number_format( count( $roster ) ); ?> attendee<?php echo count( $roster ) !== 1 ? 's' : ''; ?></span>
				</h2>

				<?php if ( empty( $roster ) ) : ?>
					<p>No roster data found for this add-on. If attendees were imported before v1.1.0, re-import the CSV to populate roster data.</p>
				<?php else : ?>

					<table class="wp-list-table widefat fixed striped oha-att-table">
						<thead>
							<tr>
								<th class="col-name">Name</th>
								<th class="col-email">Email</th>
								<th>Ticket Level</th>
								<th>Amount Paid</th>
								<th>Included in Ticket</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $roster as $r ) : ?>
							<tr>
								<td><?php echo esc_html( trim( $r->last_name . ', ' . $r->first_name, ', ' ) ); ?></td>
								<td><?php echo esc_html( $r->email ); ?></td>
								<td><?php echo esc_html( $r->ticket_level ); ?></td>
								<td><?php echo (float) $r->amount > 0 ? '$' . number_format( (float) $r->amount, 2 ) : '-'; ?></td>
								<td>
									<?php if ( (float) $r->amount == 0.00 ) : ?>
										<span class="oha-badge oha-badge-existing">Yes</span>
									<?php else : ?>
										<span class="oha-badge oha-badge-none">Paid</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

				<?php endif; ?>

			<?php elseif ( $addons ) : ?>
				<p style="color:#666">Select an add-on above to view its roster.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Settings page
	// =========================================================================
	public static function page_settings() : void {
		if ( isset( $_POST['_oha_att_settings_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['_oha_att_settings_nonce'] ), 'oha_att_settings' ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				update_option( 'oha_attendee_default_year', (int) ( $_POST['default_year'] ?? date( 'Y' ) ) );
				update_option( 'oha_attendee_uwp_form_id',  (int) ( $_POST['uwp_form_id']  ?? 4 ) );
				echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
			}
		}

		$default_year = (int) get_option( 'oha_attendee_default_year', date( 'Y' ) );
		$uwp_form_id  = (int) get_option( 'oha_attendee_uwp_form_id', 4 );
		$current_year = (int) date( 'Y' );
		$role         = get_role( 'oha_convention_attendee' );
		$role_count   = $role ? count( get_users( [ 'role' => 'oha_convention_attendee', 'fields' => 'ID', 'number' => -1 ] ) ) : 0;
		?>
		<div class="wrap oha-att-wrap">
			<h1>Attendee Hub Settings</h1>

			<form method="post">
				<?php wp_nonce_field( 'oha_att_settings', '_oha_att_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="default_year">Default Convention Year</label></th>
						<td>
							<select name="default_year" id="default_year">
								<?php for ( $y = $current_year + 1; $y >= 2020; $y-- ) : ?>
									<option value="<?php echo $y; ?>" <?php selected( $y, $default_year ); ?>><?php echo $y; ?></option>
								<?php endfor; ?>
							</select>
							<p class="description">Pre-selects this year on the Attendees, Import, and Welcome Email pages.</p>
						</td>
					</tr>
					<tr>
						<th><label for="uwp_form_id">UsersWP Form ID for new attendees</label></th>
						<td>
							<input type="number" name="uwp_form_id" id="uwp_form_id" value="<?php echo $uwp_form_id; ?>" min="1" style="width:80px">
							<p class="description">The UsersWP registration form ID assigned to newly imported accounts. Check WP Admin &gt; UsersWP &gt; User Types to confirm the correct ID. Default: 4.</p>
						</td>
					</tr>
				</table>
				<p class="submit"><button type="submit" class="button button-primary">Save Settings</button></p>
			</form>

			<hr>
			<h2>Convention Attendee Role</h2>
			<?php if ( $role ) : ?>
				<p>
					The <strong>Convention Attendee</strong> role (<code>oha_convention_attendee</code>) is registered.
					Currently <strong><?php echo number_format( $role_count ); ?></strong> user<?php echo $role_count !== 1 ? 's have' : ' has'; ?> this role.
				</p>
				<p>This role appears in JEmail campaigns under the Role filter, letting you target convention attendees directly. Attendees who already had a WP account keep their existing role.</p>
			<?php else : ?>
				<p class="oha-att-warn">Role not found. Deactivate and reactivate the plugin to re-register it.</p>
			<?php endif; ?>

			<hr>
			<h2>User Meta Keys Written on Import</h2>
			<table class="widefat" style="max-width:600px">
				<thead><tr><th>Meta Key</th><th>Content</th></tr></thead>
				<tbody>
					<tr><td><code>oha_convention_years</code></td><td>Array of years attended, e.g. [2025, 2026]</td></tr>
					<tr><td><code>oha_convention_{year}_ticket_level</code></td><td>Ticket level for that year</td></tr>
					<tr><td><code>oha_convention_{year}_add_on</code></td><td>Add-on names for that year (comma-separated summary)</td></tr>
					<tr><td><code>oha_convention_{year}_purchase_amount</code></td><td>Ticket purchase amount for that year</td></tr>
					<tr><td><code>oha_phone</code></td><td>Phone (filled only if blank)</td></tr>
					<tr><td><code>oha_address_1</code>, <code>oha_city</code>, <code>oha_state</code>, <code>oha_zip</code>, <code>oha_country</code></td><td>Address fields (filled only if blank)</td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
