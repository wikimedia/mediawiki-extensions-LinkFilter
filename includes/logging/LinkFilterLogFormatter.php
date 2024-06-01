<?php
/**
 * Logging formatter for LinkFilter's log entries.
 * This is needed to convert the numeric link types ($7 a.k.a $params[6] below) to their
 * human-readable names.
 *
 * @file
 * @date 1 June 2024
 */
class LinkFilterLogFormatter extends WikitextLogFormatter {
	/**
	 * Formats parameters intented for action message from
	 * array of all parameters. There are three hardcoded
	 * parameters (array is zero-indexed, this list not):
	 *  - 1: user name with premade link
	 *  - 2: usable for gender magic function
	 *  - 3: target page with premade link
	 * @return array
	 */
	protected function getMessageParameters() {
		if ( isset( $this->parsedParameters ) ) {
			return $this->parsedParameters;
		}

		$entry = $this->entry;
		$params = $this->extractParameters();

		// @todo CHECKME: how much of this is really needed? I just copypasted this from Comments,
		// but that logging class was created in 2013 and it's now 2024...
		$params[0] = Message::rawParam( $this->getPerformerElement() );
		$identity = method_exists( $entry, 'getPerformerIdentity' ) ?
			$entry->getPerformerIdentity()->getName() :
			// @phan-suppress-next-line PhanUndeclaredMethod
			$entry->getPerformer()->getName();
		$params[1] = $this->canView( LogPage::DELETED_USER ) ? $identity : '';

		$title = $entry->getTarget();

		if ( $entry->getSubtype() === 'submit' || $entry->getSubtype() === 'reject' ) {
			// No link here since the link was merely "suggested", one could say
			$params[2] = Message::rawParam( $title->getText() );
		} else {
			// Have the link page link to the correct page (in the NS_LINK namespace) but
			// *hide* the namespace, it'll look more grammatical and just neater that way
			$params[2] = Message::rawParam( $this->makePageLink( $title, [], $title->getText() ) );
		}

		$params[6] = Link::getLinkType( $params[6] );

		// Bad things happens if the numbers are not in correct order
		ksort( $params );
		$this->parsedParameters = $params;
		return $params;
	}
}
