<?php defined( 'ABSPATH' ) || exit; ?>

<style>
.sc-cfg-wrap { display:flex; gap:0; border:1px solid #c3c4c7; background:#fff; }
.sc-cfg-sidebar { width:200px; flex-shrink:0; border-right:1px solid #c3c4c7; background:#f6f7f7; }
.sc-cfg-sidebar a { display:block; padding:10px 14px; color:#1d2327; text-decoration:none; border-bottom:1px solid #e5e5e5; font-size:13px; }
.sc-cfg-sidebar a:hover { background:#e8eaf0; }
.sc-cfg-sidebar a.active { background:#fff; border-right:2px solid #2271b1; font-weight:600; color:#2271b1; }
.sc-cfg-main { flex:1; padding:20px; min-height:340px; }
.sc-cfg-section { display:none; }
.sc-cfg-section.active { display:block; }
.sc-cfg-keys-list { list-style:none; margin:0 0 12px; padding:0; }
.sc-cfg-keys-list li { display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.sc-cfg-keys-list input[type=text] { width:260px; }
.sc-cfg-keys-list .sc-cfg-rm { background:none; border:none; cursor:pointer; color:#b32d2e; font-size:18px; line-height:1; padding:0 4px; }
.sc-cfg-add-row { margin-bottom:12px; display:flex; gap:6px; align-items:center; }
.sc-cfg-hn { display:flex; gap:0; border-bottom:1px solid #c3c4c7; margin-bottom:20px; }
.sc-cfg-hn a { padding:8px 18px; color:#1d2327; text-decoration:none; font-size:13px; border-bottom:2px solid transparent; }
.sc-cfg-hn a:hover { color:#2271b1; }
.sc-cfg-hn a.active { color:#2271b1; border-bottom-color:#2271b1; font-weight:600; }
.sc-cfg-tab-panel { display:none; }
.sc-cfg-tab-panel.active { display:block; }
.sc-cfg-notice { display:none; margin-top:10px; padding:8px 12px; border-radius:3px; font-size:13px; }
.sc-cfg-notice.success { background:#d1e7dd; color:#0a3622; }
.sc-cfg-notice.error   { background:#f8d7da; color:#58151c; }
</style>

<div class="sc-cfg-hn" id="sc-cfg-hn">
	<a href="#" class="active" data-section="post_types"><?php esc_html_e( 'Post Types', 'space-core' ); ?></a>
	<a href="#" data-section="taxonomies"><?php esc_html_e( 'Taxonomies', 'space-core' ); ?></a>
</div>

<?php
// ---- Post Types panel ----
$first_pt = $post_types ? array_key_first( $post_types ) : null;
?>
<div class="sc-cfg-tab-panel active" id="sc-cfg-section-post_types">
	<div class="sc-cfg-wrap">
		<nav class="sc-cfg-sidebar" id="sc-cfg-sidebar-post_types">
			<?php foreach ( $post_types as $pt ) : ?>
				<a href="#"
				   data-slug="<?php echo esc_attr( $pt->name ); ?>"
				   data-type="post_types"
				   class="sc-cfg-type-link<?php echo $pt->name === $first_pt ? ' active' : ''; ?>">
					<?php echo esc_html( $pt->label ); ?>
					<small style="display:block;color:#888;font-weight:400;"><?php echo esc_html( $pt->name ); ?></small>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="sc-cfg-main" id="sc-cfg-main-post_types">
			<?php foreach ( $post_types as $pt ) : ?>
				<?php
				$saved_keys = $config['post_types'][ $pt->name ]['keys'] ?? [];
				?>
				<div class="sc-cfg-section<?php echo $pt->name === $first_pt ? ' active' : ''; ?>"
				     id="sc-cfg-pt-<?php echo esc_attr( $pt->name ); ?>"
				     data-slug="<?php echo esc_attr( $pt->name ); ?>"
				     data-type="post_types">

					<p style="margin-top:0;color:#555;font-size:13px;">
						<?php
						/* translators: %s: post type label */
						printf( esc_html__( 'Meta keys to include when translating %s.', 'space-core' ), '<strong>' . esc_html( $pt->label ) . '</strong>' );
						?>
					</p>

					<ul class="sc-cfg-keys-list">
						<?php foreach ( $saved_keys as $k ) : ?>
							<li>
								<input type="text" class="sc-cfg-key" value="<?php echo esc_attr( $k ); ?>">
								<button type="button" class="sc-cfg-rm" title="<?php esc_attr_e( 'Remove', 'space-core' ); ?>">&#x2715;</button>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="sc-cfg-add-row">
						<input type="text" class="sc-cfg-new-key" placeholder="<?php esc_attr_e( 'meta_key', 'space-core' ); ?>" style="width:220px;">
						<button type="button" class="button sc-cfg-add"><?php esc_html_e( '+ Add key', 'space-core' ); ?></button>
						<button type="button" class="button sc-cfg-load-sample"><?php esc_html_e( 'Load sample keys', 'space-core' ); ?></button>
					</div>

					<button type="button" class="button button-primary sc-cfg-save"><?php esc_html_e( 'Save', 'space-core' ); ?></button>

					<div class="sc-cfg-notice"></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<?php
// ---- Taxonomies panel ----
$first_tax = $taxonomies ? array_key_first( $taxonomies ) : null;
?>
<div class="sc-cfg-tab-panel" id="sc-cfg-section-taxonomies">
	<div class="sc-cfg-wrap">
		<nav class="sc-cfg-sidebar" id="sc-cfg-sidebar-taxonomies">
			<?php foreach ( $taxonomies as $tax ) : ?>
				<a href="#"
				   data-slug="<?php echo esc_attr( $tax->name ); ?>"
				   data-type="taxonomies"
				   class="sc-cfg-type-link<?php echo $tax->name === $first_tax ? ' active' : ''; ?>">
					<?php echo esc_html( $tax->label ); ?>
					<small style="display:block;color:#888;font-weight:400;"><?php echo esc_html( $tax->name ); ?></small>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="sc-cfg-main" id="sc-cfg-main-taxonomies">
			<?php foreach ( $taxonomies as $tax ) : ?>
				<?php
				$saved_keys = $config['taxonomies'][ $tax->name ]['keys'] ?? [];
				?>
				<div class="sc-cfg-section<?php echo $tax->name === $first_tax ? ' active' : ''; ?>"
				     id="sc-cfg-tax-<?php echo esc_attr( $tax->name ); ?>"
				     data-slug="<?php echo esc_attr( $tax->name ); ?>"
				     data-type="taxonomies">

					<p style="margin-top:0;color:#555;font-size:13px;">
						<?php
						/* translators: %s: taxonomy label */
						printf( esc_html__( 'Meta keys to include when translating %s terms.', 'space-core' ), '<strong>' . esc_html( $tax->label ) . '</strong>' );
						?>
					</p>

					<ul class="sc-cfg-keys-list">
						<?php foreach ( $saved_keys as $k ) : ?>
							<li>
								<input type="text" class="sc-cfg-key" value="<?php echo esc_attr( $k ); ?>">
								<button type="button" class="sc-cfg-rm" title="<?php esc_attr_e( 'Remove', 'space-core' ); ?>">&#x2715;</button>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="sc-cfg-add-row">
						<input type="text" class="sc-cfg-new-key" placeholder="<?php esc_attr_e( 'meta_key', 'space-core' ); ?>" style="width:220px;">
						<button type="button" class="button sc-cfg-add"><?php esc_html_e( '+ Add key', 'space-core' ); ?></button>
						<button type="button" class="button sc-cfg-load-sample"><?php esc_html_e( 'Load sample keys', 'space-core' ); ?></button>
					</div>

					<button type="button" class="button button-primary sc-cfg-save"><?php esc_html_e( 'Save', 'space-core' ); ?></button>

					<div class="sc-cfg-notice"></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<script>
(function($){
	var nonce = <?php echo wp_json_encode( $nonce ); ?>;

	// Top horizontal nav (Post Types / Taxonomies)
	$('#sc-cfg-hn a').on('click', function(e){
		e.preventDefault();
		$('#sc-cfg-hn a').removeClass('active');
		$(this).addClass('active');
		$('.sc-cfg-tab-panel').removeClass('active');
		$('#sc-cfg-section-' + $(this).data('section')).addClass('active');
	});

	// Sidebar type/taxonomy links
	$(document).on('click', '.sc-cfg-type-link', function(e){
		e.preventDefault();
		var type = $(this).data('type');
		var slug = $(this).data('slug');
		$('#sc-cfg-sidebar-' + type + ' a').removeClass('active');
		$(this).addClass('active');
		$('#sc-cfg-main-' + type + ' .sc-cfg-section').removeClass('active');
		var prefix = type === 'post_types' ? '#sc-cfg-pt-' : '#sc-cfg-tax-';
		$(prefix + slug).addClass('active');
	});

	// Add key row
	$(document).on('click', '.sc-cfg-add', function(){
		var $section = $(this).closest('.sc-cfg-section');
		var val = $section.find('.sc-cfg-new-key').val().trim();
		if ( ! val ) return;
		$section.find('.sc-cfg-keys-list').append(
			'<li><input type="text" class="sc-cfg-key" value="' + val.replace(/"/g, '&quot;') + '"><button type="button" class="sc-cfg-rm" title="Remove">&#x2715;</button></li>'
		);
		$section.find('.sc-cfg-new-key').val('');
	});

	// Allow Enter key in new-key input to add
	$(document).on('keydown', '.sc-cfg-new-key', function(e){
		if ( e.key === 'Enter' ) { e.preventDefault(); $(this).siblings('.sc-cfg-add').trigger('click'); }
	});

	// Remove key row
	$(document).on('click', '.sc-cfg-rm', function(){
		$(this).closest('li').remove();
	});

	// Load sample keys
	$(document).on('click', '.sc-cfg-load-sample', function(){
		var $section = $(this).closest('.sc-cfg-section');
		var slug     = $section.data('slug');
		var type     = $section.data('type');
		var $btn     = $(this);
		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Loading…', 'space-core' ) ); ?>');

		$.post(ajaxurl, {
			action:      'sc_translation_sample_keys',
			_ajax_nonce: nonce,
			config_type: type,
			slug:        slug
		}, function(res){
			$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Load sample keys', 'space-core' ) ); ?>');
			if ( ! res.success ) return;
			var keys = res.data.keys || [];
			if ( ! keys.length ) {
				alert('<?php echo esc_js( __( 'No auto-detectable meta keys found for this type.', 'space-core' ) ); ?>');
				return;
			}
			// Merge with existing, deduplicate
			var existing = [];
			$section.find('.sc-cfg-key').each(function(){ existing.push($(this).val()); });
			$.each(keys, function(i, k){
				if ( existing.indexOf(k) === -1 ) {
					$section.find('.sc-cfg-keys-list').append(
						'<li><input type="text" class="sc-cfg-key" value="' + k.replace(/"/g, '&quot;') + '"><button type="button" class="sc-cfg-rm" title="Remove">&#x2715;</button></li>'
					);
				}
			});
		});
	});

	// Save
	$(document).on('click', '.sc-cfg-save', function(){
		var $section = $(this).closest('.sc-cfg-section');
		var slug     = $section.data('slug');
		var type     = $section.data('type');
		var $notice  = $section.find('.sc-cfg-notice');
		var $btn     = $(this);
		var keys     = [];
		$section.find('.sc-cfg-key').each(function(){
			var v = $(this).val().trim();
			if ( v ) keys.push(v);
		});

		$btn.prop('disabled', true);
		$notice.hide();

		$.post(ajaxurl, {
			action:      'sc_translation_save_config',
			_ajax_nonce: nonce,
			config_type: type,
			slug:        slug,
			keys:        keys
		}, function(res){
			$btn.prop('disabled', false);
			$notice.removeClass('success error')
			       .addClass( res.success ? 'success' : 'error' )
			       .text( res.success ? res.data.message : (res.data ? res.data.message : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>') )
			       .show();
			setTimeout(function(){ $notice.fadeOut(); }, 3000);
		});
	});

})(jQuery);
</script>
