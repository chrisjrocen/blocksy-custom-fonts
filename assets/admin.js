/* global bcfData, bcfVariationLabels, wp */

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	function post( action, body ) {
		const params = new URLSearchParams( { action, nonce: bcfData.nonce, ...body } );
		return fetch( bcfData.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString(),
		} ).then( ( r ) => r.json() );
	}

	function setMsg( el, text, isError ) {
		el.textContent = text;
		el.style.color = isError ? '#d63638' : '#00a32a';
	}

	function showSpinner( btn, spinner, on ) {
		btn.disabled = on;
		spinner.classList.toggle( 'is-active', on );
	}

	function el( tag, attrs, children ) {
		const node = document.createElement( tag );
		if ( attrs ) {
			Object.entries( attrs ).forEach( ( [ k, v ] ) => {
				if ( k === 'className' ) node.className = v;
				else if ( k === 'textContent' ) node.textContent = v;
				else if ( k === 'style' ) node.style.cssText = v;
				else node.setAttribute( k, v );
			} );
		}
		if ( children ) children.forEach( ( c ) => c && node.appendChild( c ) );
		return node;
	}

	// -------------------------------------------------------------------------
	// Media uploader
	// -------------------------------------------------------------------------

	function openMediaUploader( onSelect ) {
		const frame = wp.media( {
			title: bcfData.mediaTitle,
			button: { text: bcfData.mediaButton },
			multiple: false,
		} );
		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			onSelect( attachment );
		} );
		frame.open();
	}

	// -------------------------------------------------------------------------
	// Build a variation <tr> using safe DOM methods
	// -------------------------------------------------------------------------

	function buildVariationRow( familyIndex, varIndex, code, url ) {
		const label    = bcfVariationLabels[ code ] || code;
		const filename = url.split( '/' ).pop();

		const link = el( 'a', { href: url, target: '_blank', textContent: filename } );

		const delBtn = el( 'button', {
			type: 'button',
			className: 'button button-small bcf-delete-variation',
			textContent: 'Remove',
			'data-family-index': String( familyIndex ),
			'data-var-index': String( varIndex ),
		} );

		const tr = el( 'tr', { 'data-var-index': String( varIndex ) }, [
			el( 'td', { textContent: label } ),
			el( 'td', {}, [ link ] ),
			el( 'td', {}, [ delBtn ] ),
		] );

		return tr;
	}

	// -------------------------------------------------------------------------
	// Build the "add variation" toolbar
	// -------------------------------------------------------------------------

	function buildAddVariationBar( familyIndex ) {
		const select = el( 'select', { className: 'bcf-var-code' } );
		Object.entries( bcfVariationLabels ).forEach( ( [ code, label ] ) => {
			const option = el( 'option', { value: code, textContent: label } );
			select.appendChild( option );
		} );

		const chooseBtn = el( 'button', {
			type: 'button',
			className: 'button bcf-choose-file',
			textContent: 'Choose Font File…',
		} );

		const filenameSpan = el( 'span', {
			className: 'bcf-chosen-filename',
			style: 'font-style:italic;',
		} );

		const attInput = el( 'input', { type: 'hidden', className: 'bcf-chosen-attachment-id', value: '' } );
		const urlInput = el( 'input', { type: 'hidden', className: 'bcf-chosen-url', value: '' } );

		const saveBtn = el( 'button', {
			type: 'button',
			className: 'button button-primary bcf-save-variation',
			textContent: 'Add Variation',
			'data-family-index': String( familyIndex ),
		} );

		const spinner = el( 'span', {
			className: 'bcf-spinner spinner',
			style: 'float:none;margin-top:0;',
		} );

		const msg = el( 'span', { className: 'bcf-msg' } );

		const bar = el( 'div', {
			className: 'bcf-add-variation',
			style: 'margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;',
		}, [ select, chooseBtn, filenameSpan, attInput, urlInput, saveBtn, spinner, msg ] );

		return bar;
	}

	// -------------------------------------------------------------------------
	// Build an entire family card using safe DOM methods
	// -------------------------------------------------------------------------

	function buildFamilyCard( family, index ) {
		// Header.
		const heading = el( 'h2', { className: 'hndle', textContent: family } );

		const delFamilyBtn = el( 'button', {
			type: 'button',
			className: 'button button-link-delete bcf-delete-family',
			textContent: 'Delete Family',
			'data-index': String( index ),
			'data-family': family,
		} );

		const handleActions = el( 'div', { className: 'handle-actions' }, [ delFamilyBtn ] );
		const header = el( 'div', { className: 'postbox-header' }, [ heading, handleActions ] );

		// Variations table.
		const thead = el( 'thead', {}, [
			el( 'tr', {}, [
				el( 'th', { textContent: 'Variation' } ),
				el( 'th', { textContent: 'File' } ),
				el( 'th', { textContent: 'Remove', style: 'width:80px;' } ),
			] ),
		] );
		const tbody = el( 'tbody', { className: 'bcf-variations-tbody' } );
		const table = el( 'table', { className: 'wp-list-table widefat fixed striped' }, [ thead, tbody ] );

		const inside = el( 'div', { className: 'inside' }, [
			table,
			buildAddVariationBar( index ),
		] );

		const card = el( 'div', {
			className: 'bcf-family-card postbox',
			'data-index': String( index ),
		}, [ header, inside ] );

		return card;
	}

	// -------------------------------------------------------------------------
	// Event delegation on the families list container
	// -------------------------------------------------------------------------

	const list       = document.getElementById( 'bcf-families-list' );
	const noFontsMsg = document.getElementById( 'bcf-no-fonts' );

	if ( list ) {
		list.addEventListener( 'click', function ( e ) {
			const target = e.target;

			// ---- Choose font file ----
			if ( target.classList.contains( 'bcf-choose-file' ) ) {
				const bar = target.closest( '.bcf-add-variation' );
				openMediaUploader( function ( attachment ) {
					bar.querySelector( '.bcf-chosen-attachment-id' ).value = attachment.id;
					bar.querySelector( '.bcf-chosen-url' ).value = attachment.url;
					const name = attachment.filename || attachment.url.split( '/' ).pop();
					bar.querySelector( '.bcf-chosen-filename' ).textContent = name;
				} );
				return;
			}

			// ---- Save variation ----
			if ( target.classList.contains( 'bcf-save-variation' ) ) {
				const bar         = target.closest( '.bcf-add-variation' );
				const code        = bar.querySelector( '.bcf-var-code' ).value;
				const attId       = bar.querySelector( '.bcf-chosen-attachment-id' ).value;
				const msg         = bar.querySelector( '.bcf-msg' );
				const spinner     = bar.querySelector( '.bcf-spinner' );
				const familyIndex = target.dataset.familyIndex;

				if ( ! attId ) {
					setMsg( msg, 'Please choose a font file first.', true );
					return;
				}

				showSpinner( target, spinner, true );

				post( 'bcf_add_variation', {
					family_index: familyIndex,
					code,
					attachment_id: attId,
				} ).then( ( res ) => {
					showSpinner( target, spinner, false );

					if ( ! res.success ) {
						setMsg( msg, ( res.data && res.data.message ) || 'Error.', true );
						return;
					}

					const card     = target.closest( '.bcf-family-card' );
					const tbody    = card.querySelector( '.bcf-variations-tbody' );
					const rCode    = res.data.code;
					const url      = res.data.url;
					const varIndex = res.data.var_index;
					const replaced = res.data.replaced;

					if ( replaced ) {
						const existingRow = tbody.querySelector( '[data-var-index="' + varIndex + '"]' );
						if ( existingRow ) {
							existingRow.replaceWith( buildVariationRow( familyIndex, varIndex, rCode, url ) );
						}
					} else {
						tbody.appendChild( buildVariationRow( familyIndex, varIndex, rCode, url ) );
					}

					// Reset the bar.
					bar.querySelector( '.bcf-chosen-attachment-id' ).value = '';
					bar.querySelector( '.bcf-chosen-url' ).value = '';
					bar.querySelector( '.bcf-chosen-filename' ).textContent = '';
					setMsg( msg, 'Saved.', false );
					setTimeout( function () { msg.textContent = ''; }, 2000 );
				} ).catch( function () {
					showSpinner( target, spinner, false );
					setMsg( msg, 'Request failed.', true );
				} );
				return;
			}

			// ---- Delete variation ----
			if ( target.classList.contains( 'bcf-delete-variation' ) ) {
				if ( ! window.confirm( 'Remove this variation?' ) ) return;

				const familyIndex = target.dataset.familyIndex;
				const varIndex    = target.dataset.varIndex;
				const tr          = target.closest( 'tr' );

				post( 'bcf_delete_variation', { family_index: familyIndex, var_index: varIndex } )
					.then( function ( res ) {
						if ( ! res.success ) return;
						tr.remove();
						// Re-index remaining rows.
						const card  = list.querySelector( '.bcf-family-card[data-index="' + familyIndex + '"]' );
						const tbody = card && card.querySelector( '.bcf-variations-tbody' );
						if ( tbody ) {
							Array.from( tbody.querySelectorAll( 'tr' ) ).forEach( function ( row, i ) {
								row.dataset.varIndex = i;
								const btn = row.querySelector( '.bcf-delete-variation' );
								if ( btn ) btn.dataset.varIndex = i;
							} );
						}
					} );
				return;
			}

			// ---- Delete family ----
			if ( target.classList.contains( 'bcf-delete-family' ) ) {
				const family      = target.dataset.family;
				const familyIndex = target.dataset.index;
				const card        = target.closest( '.bcf-family-card' );

				if ( ! window.confirm( 'Delete the font family "' + family + '" and all its variations?' ) ) return;

				post( 'bcf_delete_family', { family_index: familyIndex } ).then( function ( res ) {
					if ( ! res.success ) return;
					card.remove();
					// Re-index remaining cards.
					Array.from( list.querySelectorAll( '.bcf-family-card' ) ).forEach( function ( c, i ) {
						c.dataset.index = i;
						const delBtn = c.querySelector( '.bcf-delete-family' );
						if ( delBtn ) delBtn.dataset.index = i;
						const saveBtn = c.querySelector( '.bcf-save-variation' );
						if ( saveBtn ) saveBtn.dataset.familyIndex = i;
						c.querySelectorAll( '.bcf-delete-variation' ).forEach( function ( b ) {
							b.dataset.familyIndex = i;
						} );
					} );
					if ( noFontsMsg && ! list.querySelector( '.bcf-family-card' ) ) {
						noFontsMsg.style.display = '';
					}
				} );
				return;
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Add new family form
	// -------------------------------------------------------------------------

	const addForm = document.getElementById( 'bcf-add-family-form' );

	if ( addForm ) {
		addForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			const input   = addForm.querySelector( '#bcf-family-name' );
			const btn     = addForm.querySelector( 'button[type="submit"]' );
			const spinner = addForm.querySelector( '.bcf-spinner' );
			const msg     = addForm.querySelector( '.bcf-msg' );
			const family  = input.value.trim();

			if ( ! family ) return;

			showSpinner( btn, spinner, true );

			post( 'bcf_add_family', { family } ).then( function ( res ) {
				showSpinner( btn, spinner, false );

				if ( ! res.success ) {
					setMsg( msg, ( res.data && res.data.message ) || 'Error.', true );
					return;
				}

				setMsg( msg, 'Font family added.', false );
				input.value = '';

				const newFamily = res.data.family;
				const index     = res.data.index;
				const card      = buildFamilyCard( newFamily, index );
				list.appendChild( card );

				if ( noFontsMsg ) noFontsMsg.style.display = 'none';
				setTimeout( function () { msg.textContent = ''; }, 2000 );
			} ).catch( function () {
				showSpinner( btn, spinner, false );
				setMsg( msg, 'Request failed.', true );
			} );
		} );
	}
} )();
