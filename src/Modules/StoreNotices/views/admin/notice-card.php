<?php

defined( 'ABSPATH' ) || exit;
?>
<div id="<?php echo esc_attr( $card_dom_id ); ?>" class="<?php echo esc_attr( trim( 'postbox sc-sn-card ' . $state_class ) ); ?>" data-id="<?php echo esc_attr( (string) $id ); ?>">
    <div class="postbox-header">
        <h2 class="hndle"><span class="sc-sn-card-title"><?php echo esc_html( $card_title ); ?></span></h2>
        <div class="handle-actions hide-if-no-js">
            <button type="button" class="handlediv" aria-expanded="<?php echo $is_closed ? 'false' : 'true'; ?>">
                <span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s', 'space-core' ), $card_title ) ); ?></span>
                <span class="toggle-indicator" aria-hidden="true"></span>
            </button>
        </div>
    </div>
    <div class="inside">
        <div class="sc-sn-fields-grid">
            <div>
                <label class="sc-sn-field-label"><?php esc_html_e( 'Title (EN)', 'space-core' ); ?></label>
                <input type="text" class="sc-sn-title-en widefat" value="<?php echo esc_attr( $title['en'] ?? '' ); ?>">
            </div>
            <div>
                <label class="sc-sn-field-label"><?php esc_html_e( 'Title (AR)', 'space-core' ); ?></label>
                <input type="text" class="sc-sn-title-ar widefat" dir="rtl" value="<?php echo esc_attr( $title['ar'] ?? '' ); ?>">
            </div>
            <div>
                <label class="sc-sn-field-label"><?php esc_html_e( 'Message (EN)', 'space-core' ); ?></label>
                <textarea class="sc-sn-msg-en widefat" rows="2"><?php echo esc_textarea( $message['en'] ?? '' ); ?></textarea>
            </div>
            <div>
                <label class="sc-sn-field-label"><?php esc_html_e( 'Message (AR)', 'space-core' ); ?></label>
                <textarea class="sc-sn-msg-ar widefat" rows="2" dir="rtl"><?php echo esc_textarea( $message['ar'] ?? '' ); ?></textarea>
            </div>
            <div>
                <label><?php esc_html_e( 'Icon (Material Icon name)', 'space-core' ); ?></label>
                <input type="text" class="sc-sn-icon regular-text" value="<?php echo esc_attr( $notice['icon'] ?? '' ); ?>" placeholder="local_offer">
            </div>
            <div class="sc-sn-colors">
                <div><label><?php esc_html_e( 'Background', 'space-core' ); ?></label><br><input type="color" class="sc-sn-bg-color" value="<?php echo esc_attr( $bg ); ?>"></div>
                <div><label><?php esc_html_e( 'Text', 'space-core' ); ?></label><br><input type="color" class="sc-sn-text-color" value="<?php echo esc_attr( $text ); ?>"></div>
            </div>
            <div>
                <label><?php esc_html_e( 'Start At', 'space-core' ); ?></label>
                <input type="datetime-local" class="sc-sn-start" value="<?php echo esc_attr( ! empty( $notice['start_at'] ) ? str_replace( ' ', 'T', substr( $notice['start_at'], 0, 16 ) ) : '' ); ?>">
            </div>
            <div>
                <label><?php esc_html_e( 'End At', 'space-core' ); ?></label>
                <input type="datetime-local" class="sc-sn-end" value="<?php echo esc_attr( ! empty( $notice['end_at'] ) ? str_replace( ' ', 'T', substr( $notice['end_at'], 0, 16 ) ) : '' ); ?>">
            </div>
            <div class="sc-sn-toggles">
                <label><input type="checkbox" class="sc-sn-active" <?php checked( ! $id || ! empty( $notice['is_active'] ) ); ?>><?php esc_html_e( 'Active', 'space-core' ); ?></label>
                <label><input type="checkbox" class="sc-sn-dismissible" <?php checked( $id ? ! empty( $notice['is_dismissible'] ) : true ); ?>><?php esc_html_e( 'Dismissible', 'space-core' ); ?></label>
            </div>
        </div>

        <details class="sc-sn-targeting">
            <summary class="sc-sn-targeting-summary"><?php esc_html_e( 'Page Targeting (leave all empty = show everywhere)', 'space-core' ); ?></summary>
            <div class="sc-sn-targeting-grid">
                <?php foreach ( $dims as $dim ) : $selected_items = $this->resolve_selected_titles( $dim['type'], $dim['ids'] ); ?>
                    <div class="sc-sn-dim">
                        <label class="sc-sn-field-label"><?php echo esc_html( $dim['label'] ); ?></label>
                        <label class="sc-sn-all-label">
                            <input type="checkbox" class="sc-sn-all-chk <?php echo esc_attr( $dim['all_chk'] ); ?>" data-target="<?php echo esc_attr( $dim['ms_cls'] ); ?>" <?php checked( $dim['all_val'] ); ?>>
                            <?php esc_html_e( 'All', 'space-core' ); ?>
                        </label>
                        <select multiple class="sc-sn-select2 <?php echo esc_attr( $dim['ms_cls'] ); ?><?php echo $dim['all_val'] ? ' sc-sn-is-hidden' : ''; ?>" data-type="<?php echo esc_attr( $dim['type'] ); ?>">
                            <?php foreach ( $selected_items as $opt ) : ?>
                                <option value="<?php echo esc_attr( (string) $opt['id'] ); ?>" selected><?php echo esc_html( $opt['text'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>

        <div class="sc-sn-preview">
            <span class="sc-sn-preview-icon sc-notice-material-icon<?php echo empty( $notice['icon'] ) ? ' sc-sn-is-hidden' : ''; ?>" aria-hidden="true"><?php echo esc_html( $notice['icon'] ?? '' ); ?></span>
            <div class="sc-sn-preview-body">
                <strong class="sc-sn-preview-title"><?php echo esc_html( $title['en'] ?? '' ); ?></strong>
                <span class="sc-sn-preview-msg"><?php echo esc_html( $message['en'] ?? '' ); ?></span>
            </div>
        </div>

        <div class="sc-sn-card-actions">
            <button type="button" class="button button-primary sc-sn-save"><?php esc_html_e( 'Save', 'space-core' ); ?></button>
            <button type="button" class="button sc-sn-delete"><?php esc_html_e( 'Delete', 'space-core' ); ?></button>
            <span class="sc-sn-status"></span>
        </div>
    </div>
</div>
