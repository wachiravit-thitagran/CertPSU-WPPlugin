<?php
/**
 * Renders settings fields (shared by the course metabox and defaults page).
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Admin;

use CertPSU\TutorLMS\Settings\Course_Settings;

/**
 * Outputs HTML form controls for the settings schema.
 */
final class Field_Renderer {

	/**
	 * Constructor.
	 *
	 * @param string $name_prefix Form field name prefix, e.g. "certpsu_course".
	 */
	public function __construct( private string $name_prefix ) {}

	/**
	 * Render all sections.
	 *
	 * @param array<string,mixed> $values Current values.
	 * @return void
	 */
	public function render_all( array $values ): void {
		foreach ( Course_Settings::schema() as $section_key => $section ) {
			echo '<div class="certpsu-section" data-section="' . esc_attr( $section_key ) . '">';
			echo '<h3 class="certpsu-section-title">' . esc_html( $section['title'] ) . '</h3>';
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $section['fields'] as $key => $field ) {
				$this->render_row( $key, $field, $values[ $key ] ?? null );
			}
			echo '</tbody></table>';
			echo '</div>';
		}
	}

	/**
	 * Render one field row.
	 *
	 * @param string              $key   Field key.
	 * @param array<string,mixed> $field Field definition.
	 * @param mixed               $value Current value.
	 * @return void
	 */
	private function render_row( string $key, array $field, mixed $value ): void {
		$type  = $field['type'] ?? 'text';
		$id    = $this->name_prefix . '_' . $key;
		$label = (string) ( $field['label'] ?? $key );

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';

		switch ( $type ) {
			case 'checkbox':
				$this->checkbox( $id, $key, (bool) $value, $label );
				break;
			case 'textarea':
				$this->textarea( $id, $key, (string) ( is_string( $value ) ? $value : '' ) );
				break;
			case 'date':
				$this->input( $id, $key, (string) ( is_string( $value ) ? $value : '' ), 'date' );
				break;
			case 'select':
				$this->select( $id, $key, (string) ( is_string( $value ) ? $value : '' ), $field['options'] ?? array() );
				break;
			case 'list':
				$this->list_field( $id, $key, is_array( $value ) ? $value : array() );
				break;
			case 'endorsers':
				$this->endorsers( $key, is_array( $value ) ? $value : array() );
				break;
			case 'text':
			default:
				$this->input( $id, $key, (string) ( is_string( $value ) ? $value : '' ), 'text' );
				break;
		}

		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Field name attribute.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function name( string $key ): string {
		return $this->name_prefix . '[' . $key . ']';
	}

	/**
	 * Text/date input.
	 *
	 * @param string $id    Element id.
	 * @param string $key   Field key.
	 * @param string $value Value.
	 * @param string $type  Input type.
	 * @return void
	 */
	private function input( string $id, string $key, string $value, string $type ): void {
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $this->name( $key ) ),
			esc_attr( $value )
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param string $id      Element id.
	 * @param string $key     Field key.
	 * @param bool   $checked Checked.
	 * @param string $label   Label.
	 * @return void
	 */
	private function checkbox( string $id, string $key, bool $checked, string $label ): void {
		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
			esc_attr( $id ),
			esc_attr( $this->name( $key ) ),
			checked( $checked, true, false ),
			esc_html__( 'Enable', 'certpsu-tutorlms' )
		);
	}

	/**
	 * Textarea.
	 *
	 * @param string $id    Element id.
	 * @param string $key   Field key.
	 * @param string $value Value.
	 * @return void
	 */
	private function textarea( string $id, string $key, string $value ): void {
		printf(
			'<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $this->name( $key ) ),
			esc_textarea( $value )
		);
	}

	/**
	 * Select.
	 *
	 * @param string                $id      Element id.
	 * @param string                $key     Field key.
	 * @param string                $value   Selected value.
	 * @param array<string,string>  $options Options.
	 * @return void
	 */
	private function select( string $id, string $key, string $value, array $options ): void {
		printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $this->name( $key ) ) );
		foreach ( $options as $opt_value => $opt_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $opt_value ),
				selected( $value, (string) $opt_value, false ),
				esc_html( (string) $opt_label )
			);
		}
		echo '</select>';
	}

	/**
	 * Newline-separated list field.
	 *
	 * @param string           $id    Element id.
	 * @param string           $key   Field key.
	 * @param array<int,mixed> $value Values.
	 * @return void
	 */
	private function list_field( string $id, string $key, array $value ): void {
		$text = implode( "\n", array_map( 'strval', $value ) );
		printf(
			'<textarea id="%1$s" name="%2$s" rows="3" class="large-text" placeholder="%4$s">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $this->name( $key ) ),
			esc_textarea( $text ),
			esc_attr__( 'One per line', 'certpsu-tutorlms' )
		);
	}

	/**
	 * Endorsers repeater.
	 *
	 * @param string                          $key   Field key (always "endorsers").
	 * @param array<int,array<string,string>> $value Rows.
	 * @return void
	 */
	private function endorsers( string $key, array $value ): void {
		$base = $this->name_prefix . '[' . $key . ']';

		echo '<div class="certpsu-endorsers" data-base="' . esc_attr( $base ) . '">';
		echo '<div class="certpsu-endorsers-rows">';

		if ( array() === $value ) {
			$this->endorser_row( $base, 0, array() );
		} else {
			foreach ( array_values( $value ) as $i => $row ) {
				$this->endorser_row( $base, (int) $i, is_array( $row ) ? $row : array() );
			}
		}

		echo '</div>';
		echo '<p><button type="button" class="button certpsu-add-endorser">' . esc_html__( 'Add endorser', 'certpsu-tutorlms' ) . '</button></p>';

		// Row template for JS cloning (index placeholder __i__).
		echo '<script type="text/html" class="certpsu-endorser-template">';
		$this->endorser_row( $base, '__i__', array() );
		echo '</script>';
		echo '</div>';
	}

	/**
	 * Single endorser row.
	 *
	 * @param string              $base Base name.
	 * @param int|string          $i    Row index (or __i__ placeholder).
	 * @param array<string,string> $row Row data.
	 * @return void
	 */
	private function endorser_row( string $base, int|string $i, array $row ): void {
		$n = static fn( string $f ): string => $base . '[' . $i . '][' . $f . ']';

		echo '<div class="certpsu-endorser-row">';
		$this->endorser_text( $n( 'endorser_id' ), (string) ( $row['endorser_id'] ?? '' ), __( 'endorser_id', 'certpsu-tutorlms' ) );
		$this->endorser_text( $n( 'user' ), (string) ( $row['user'] ?? '' ), __( 'user id', 'certpsu-tutorlms' ) );
		$this->endorser_text( $n( 'name' ), (string) ( $row['name'] ?? '' ), __( 'name', 'certpsu-tutorlms' ) );
		$this->endorser_text( $n( 'position' ), (string) ( $row['position'] ?? '' ), __( 'position', 'certpsu-tutorlms' ) );

		printf(
			'<select name="%1$s">%2$s</select>',
			esc_attr( $n( 'endorse_requirement' ) ),
			$this->options_html( array( 'required' => 'required', 'not_required' => 'not required' ), (string) ( $row['endorse_requirement'] ?? 'required' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf(
			'<select name="%1$s">%2$s</select>',
			esc_attr( $n( 'auto_send_mail_to_endorse' ) ),
			$this->options_html( array( 'auto' => 'auto', 'not_auto' => 'not auto' ), (string) ( $row['auto_send_mail_to_endorse'] ?? 'auto' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		echo '<button type="button" class="button-link certpsu-remove-endorser">' . esc_html__( 'Remove', 'certpsu-tutorlms' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Small text input for an endorser field.
	 *
	 * @param string $name        Field name.
	 * @param string $value       Value.
	 * @param string $placeholder Placeholder.
	 * @return void
	 */
	private function endorser_text( string $name, string $value, string $placeholder ): void {
		printf(
			'<input type="text" name="%1$s" value="%2$s" placeholder="%3$s" />',
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * Build <option> HTML.
	 *
	 * @param array<string,string> $options  Options.
	 * @param string               $selected Selected.
	 * @return string
	 */
	private function options_html( array $options, string $selected ): string {
		$html = '';
		foreach ( $options as $value => $label ) {
			$html .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $value ),
				selected( $selected, (string) $value, false ),
				esc_html( (string) $label )
			);
		}
		return $html;
	}
}
