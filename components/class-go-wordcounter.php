<?php

/**
 * Implements various WordPress hooks to add server-side word counter
 * functionality.
 */
class GO_WordCounter
{
	/**
	 * Initialize the plugin and register all the hooks.
	 */
	public function __construct()
	{
		// 	Register hooks
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'submitpost_box', array( $this, 'submitpost_box' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );
		add_filter( 'manage_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'custom_column' ), 10, 2 );

		// Disable WordPress.org counter
		add_action( 'wp_print_scripts', array( $this, 'disable_default' ), 100 );
	} // end __construct

	/**
	 * Removes the builtin wordcount script; ours is more accurate aaaaaaaand
	 * faster.
	 * 		That's just rude, Kyle. Just because numbers aren't words... :-P
	 */
	public function disable_default()
	{
		wp_deregister_script('word-count');
	} // end disable_default

	/**
	 * Implements the admin_enqueue_scripts action in WordPress to add CSS and JS.
	 */
	public function admin_enqueue_scripts()
	{
		$post = get_post( $post_id = false );
		if ( ! is_object( $post ) )
			return; // Bail

		$js_min = ( defined( 'GO_DEV' ) && GO_DEV ) ? 'lib' : 'min';

		$wordcountorig = $this->get_word_count( $post->ID );
		$excerpt_wordcountorig = $this->get_excerpt_word_count( $post->ID );

		$wordcounttext = 'Word count: <span id="wordcount">&hellip;</span> ';
		if ( $wordcountorig )
		{
			$wordcounttext .= '<span class="wordcount-saved-previously">(' . number_format( $wordcountorig ) . ' server-side)</span>';
		} // end if

		$excerpt_wordcounttext = 'Excerpt word count: <span class="excerpt_wordcount">&hellip;</span> ';
		if ( $excerpt_wordcountorig )
		{
			$excerpt_wordcounttext .= '<span class="wordcount-saved-previously">(' . number_format( $excerpt_wordcountorig ) . ' server-side)</span>';
		} // end if

		wp_register_style( 'go-wordcounter', plugins_url( 'css/go-wordcounter.css', __FILE__ ), false, '1.0' );

		wp_enqueue_script( 'go-wordcounter', plugins_url( 'js/' . $js_min . '/go-wordcounter.js', __FILE__ ) );
		wp_localize_script( 'go-wordcounter', 'go_wordcounter', array( 'text' => $wordcounttext, 'excerpt' => $excerpt_wordcounttext ) );
	} // end admin_enqueue_scripts

	/**
	 * Implements the save_post action in WordPress to calculate and store the
	 * word count in a post meta field.
	 * @return
	 * @param object $post_id
	 */
	public function save_post( $post_id )
	{
		// Check that this isn't an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		{
			return;
		}// end if

		$post = get_post( $post_id );
		if ( ! is_object( $post ) )
		{
			return;
		}// end if

		// check post type matches what you intend
		$whitelisted_post_types = array( 'post', 'page' );
		if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, $whitelisted_post_types ) )
		{
			return;
		}// end if

		// Don't run on post revisions (almost always happens just before the real post is saved)
		if ( wp_is_post_revision( $post->ID ) )
		{
			return;
		}// end if

		// Check the permissions
		if ( ! current_user_can( 'edit_post', $post->ID  ) )
		{
			return;
		}// end if

		$this->refreshCounts( $post, true );
	} // end save_post

	/**
	 * Implements the submitpost_box action in WordPress to add the word count
	 * details to the Edit Post page.
	 * @return
	 */
	public function submitpost_box()
	{
		global $post;
		$wordcount = get_post_meta( $post->ID, 'giga_wordcount', true );
		if ( $wordcount )
		{
		?>
		<noscript>
			<div class="side-info">
				<h5>Word Count</h5>
				<?php if( $wordcount ): ?>
					<p>Server word count: <strong><?php echo $wordcount; ?></strong></p>
				<?php endif; ?>
			</div>
		</noscript>
		<?php
		} // end if
	} // end submitpost_box

	/**
	 * Implements the manage_post_columns filter in WordPress to add a custom
	 * column heading to the table on the Manage Posts page.
	 * @return
	 * @param object $columns
	 */
	public function columns( $columns )
	{
		$columns['giga_wordcount'] = __( 'Words' );
		return $columns;
	} // end columns

	/**
	 * Implements the manage_posts_custom_column action in WordPress to fill in
	 * the Word Count column in the table on the Manage Posts page.
	 * @return
	 * @param object $column_id
	 * @param object $post_id
	 */
	public function custom_column( $column_id, $post_id )
	{
		if($column_id == 'giga_wordcount')
		{
			$wordcount = $this->get_word_count( $post_id );
			$excerpt_wordcount = $this->get_excerpt_word_count( $post_id );
			echo number_format( $excerpt_wordcount ) . '<br />' . number_format( $wordcount );
		} // end if
	} // end custom_column

	/**
	 * get word count for a post
	 * @param $post WP_Post object
	 * @return int
	 */
	public function get_word_count( $post = false )
	{
		if ( $post = get_post( $post ) )
		{
			$this->refreshCounts( $post );
			return (int) get_post_meta($post->ID, 'giga_wordcount', true );
		} // end if
	} // end get_word_count

	/**
	 * get word count for an excerpt
	 * @param $post WP_Post object
	 * @return int
	 */
	public function get_excerpt_word_count( $post = false )
	{
		if ( $post = get_post( $post ) )
		{
			$this->refreshCounts( $post );
			return (int) get_post_meta( $post->ID, 'giga_excerpt_wordcount', true );
		} // end if
	} // end get_excerpt_word_count

	/**
	 * get updated counts and store that in post meta
	 * @param $post WP_Post object
	 * @param $force do this regardless of time
	 */
	private function refreshCounts($post, $force = false)
	{
		// only refreshes on the save handler (force is true) or if this file has changed
		if( $force || filemtime(__FILE__) > get_post_meta($post->ID, 'giga_wordcount_updated', true ) )
		{
			update_post_meta( $post->ID, 'giga_wordcount', $this->count_words( $post->post_content ) );
			update_post_meta( $post->ID, 'giga_excerpt_wordcount', $this->count_words( $post->post_excerpt ) );
			update_post_meta( $post->ID, 'giga_wordcount_updated', time() );
		} // end if
	} // end refreshCounts

	/**
	 * Get word count that properly reflects the number of actual words in the
	 * string, and not codes and special characters.
	 *
	 * @param string $content
	 * @return int Number of words.
	 */
	private function count_words( $content )
	{
		// Be careful! This logic affects how editors are paid. All changes must
		// be reviewed by the editorial team.

		// Remove accented characters and HTML.
		$content = remove_accents( $content );
		$content = wp_kses( $content, array() );

		// Remove shortcodes by removing words between square brackets.
		// @todo Should only remove bracketed text that matches a shortcode.
		$content = strip_shortcodes( $content );

		// Remove stock symbol codes.
		// @todo Stock symbol codes should be shortcodes and be stripped the
		// same way as other shortcodes.
		$content = preg_replace( '/[({\[]\s*s(s|tock(symbol)?)?[\s,;:=].*?[)}\]]/i', ' ', $content );

		// Replace non-breaking spaces.
		$content = preg_replace( '/&(nbsp|#160|#xA0);/i', ' ', $content );

		// Split apart words that are joined with a slash or dash (not hyphens).
		$content = preg_replace( '/&([nm]dash|#821[12]|#x201[34]);|--|\//i', ' ', $content );

		// Remove any words composed entirely of non alpha-numeric characters.
		$content = preg_replace( '/[\^\s][^\s\da-z]+\s/i', ' ', $content);

		// Remove any remaining entities.
		$content = preg_replace( '/&([a-z\d\-\.]{1,8}|#\d{1,4}|#x[\da-f]{1,4});/is', '', $content );

		// Count the words
		$count = preg_match_all( '#\S+#', $content, $matches );

		return $count;
	} // end count_words
}// end class
