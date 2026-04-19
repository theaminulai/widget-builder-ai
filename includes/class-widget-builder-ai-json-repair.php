<?php
/**
 * JSON repair utility for AI adapter responses.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repairs and extracts malformed JSON from AI model output.
 * Handles common issues: stray backslashes, markdown fences,
 * truncated output, and invalid escape sequences.
 */
class Widget_Builder_AI_JSON_Repair {

    /**
     * Attempts to extract and decode a JSON object from raw AI output.
     * Applies progressive repair strategies until one succeeds.
     *
     * @param string $content Raw text from any AI provider.
     * @return array|null Decoded array or null on total failure.
     */
    public static function extract( $content ) {
        $trimmed = trim( (string) $content );

        if ( '' === $trimmed ) {
            return null;
        }

        // Strategy 1: Direct decode — no repair needed.
        $result = self::try_decode( $trimmed );
        if ( null !== $result ) {
            return $result;
        }

        // Strategy 2: Strip markdown code fences then decode.
        $stripped = self::strip_markdown_fences( $trimmed );
        if ( $stripped !== $trimmed ) {
            $result = self::try_decode( $stripped );
            if ( null !== $result ) {
                return $result;
            }
        }

        // Strategy 3: Extract first complete {...} block.
        $extracted = self::extract_json_block( $trimmed );
        if ( null !== $extracted && $extracted !== $trimmed ) {
            $result = self::try_decode( $extracted );
            if ( null !== $result ) {
                return $result;
            }
        }

        // Strategy 4: Repair backslashes, then decode.
        $repaired = self::repair_backslashes( $trimmed );
        $result   = self::try_decode( $repaired );
        if ( null !== $result ) {
            return $result;
        }

        // Strategy 5: Strip fences + repair backslashes.
        $repaired = self::repair_backslashes( $stripped ?? $trimmed );
        $result   = self::try_decode( $repaired );
        if ( null !== $result ) {
            return $result;
        }

        // Strategy 6: Extract block + repair backslashes.
        if ( null !== $extracted ) {
            $repaired = self::repair_backslashes( $extracted );
            $result   = self::try_decode( $repaired );
            if ( null !== $result ) {
                return $result;
            }
        }

        // Strategy 7: Repair each string value individually.
        $deep_repaired = self::repair_string_values( $trimmed );
        $result        = self::try_decode( $deep_repaired );
        if ( null !== $result ) {
            return $result;
        }

        return null;
    }

    /**
     * Attempts json_decode and returns array or null.
     *
     * @param string $json JSON string.
     * @return array|null
     */
    private static function try_decode( $json ) {
        if ( ! is_string( $json ) ) {
            return null;
        }

        $json = trim( $json );
        if ( '' === $json ) {
            return null;
        }
        $decoded = json_decode( $json, true );
        return is_array( $decoded ) ? $decoded : null;
    }

    /**
     * Strips markdown code fences from content.
     *
     * @param string $content Raw content.
     * @return string Content without fences.
     */
    private static function strip_markdown_fences( $content ) {
        if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $content, $matches ) ) {
            return trim( $matches[1] );
        }
        return $content;
    }

    /**
     * Extracts the outermost {...} JSON block from content.
     *
     * @param string $content Raw content.
     * @return string|null Extracted block or null.
     */
    private static function extract_json_block( $content ) {
        $start = strpos( $content, '{' );
        $end   = strrpos( $content, '}' );

        if ( false === $start || false === $end || $end <= $start ) {
            return null;
        }

        return substr( $content, $start, $end - $start + 1 );
    }

    /**
     * Repairs stray/invalid backslash escape sequences in a JSON string.
     *
     * Valid JSON escapes: \\ \" \/ \b \f \n \r \t \uXXXX
     * Everything else (like \' or a lone \ before a letter) is invalid
     * and gets double-escaped so json_decode can handle it.
     *
     * @param string $json Raw JSON string potentially containing bad escapes.
     * @return string Repaired JSON string.
     */
    private static function repair_backslashes( $json ) {
        $json = (string) $json;

        // Only fix backslashes that are NOT already part of a valid JSON escape
        // AND are not followed by a valid JSON escape character.
        // Valid JSON escapes: \" \\ \/ \b \f \n \r \t \uXXXX
        $repaired = preg_replace(
            '/\\\\(?!["\\\\\\/bfnrtu0-9nrt])/',
            '\\\\\\\\',
            $json
        );

        return is_string( $repaired ) ? $repaired : $json;
    }

    /**
     * Deep repair: iterates over JSON string values and repairs each one.
     * Used as a last resort when the outer JSON structure has mixed issues.
     *
     * @param string $json Raw JSON string.
     * @return string Repaired JSON string.
     */

    private static function repair_string_values( $json ) {
        $json     = (string) $json;
        $repaired = preg_replace_callback(
			'/"((?:[^"\\\\]|\\\\.)*)"/s',
			function ( $matches ) {
				$value = $matches[1];
				// Fix only backslashes not part of a valid JSON escape.
				// Do NOT touch \n \r \t \\ \" \/ \uXXXX sequences.
				$repaired = preg_replace(
					'/\\\\(?!["\\\\\\/bfnrtu0-9])/',
					'\\\\\\\\',
					$value
				);
				return '"' . $repaired . '"';
			},
			$json
		);

        return is_string( $repaired ) ? $repaired : $json;
	}
}