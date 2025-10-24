<?php
/**
 *  Dev Meta Box template.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
// phpcs:disable Squiz.PHP.EmbeddedPhp
if ( ! empty( $errors ) ) : ?>
<ul>
	<?php foreach ( $errors as $error_message ) : ?>
		<li><span class="dashicons dashicons-warning" style="color: red;"></span> <?php echo esc_html( $error_message ); ?></li>
	<?php endforeach; ?>
</ul>

<p>This product can not appear in the feed.</p>

<hr />
<?php else : ?>
<p>
	<span class="dashicons dashicons-yes" style="color: green;"></span>
	No errors
</p>
<?php endif; ?>

<pre style="overflow: hidden; margin: 0"><code style="display: block; white-space: pre-wrap;"><?php
	// Tags are on the same line as otherwise there is some spacing within the <pre> tag.
	echo wp_json_encode( $mapped_data, JSON_PRETTY_PRINT );
?></code></pre>
