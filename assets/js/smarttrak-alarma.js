( () => {
	'use strict';

	const modal = document.querySelector( '.smarttrak-alarma' );

	if ( ! modal ) {
		return;
	}

	const form = modal.querySelector( '.smarttrak-alarma__form' );
	const message = modal.querySelector( '.smarttrak-alarma__message' );

	const openModal = () => {
		modal.hidden = false;
		document.body.classList.add( 'smarttrak-alarma-lock' );
		const phoneField = form?.querySelector( 'input[name="phone"]' );
		if ( phoneField ) {
			window.setTimeout( () => phoneField.focus(), 50 );
		}
	};

	const closeModal = () => {
		modal.hidden = true;
		document.body.classList.remove( 'smarttrak-alarma-lock' );
		if ( message ) {
			message.hidden = true;
			message.textContent = '';
			message.classList.remove( 'is-error', 'is-success' );
		}
	};

	document.addEventListener( 'click', ( event ) => {
		const openTrigger = event.target.closest( '[data-smarttrak-alarma-open]' );
		const closeTrigger = event.target.closest( '[data-smarttrak-alarma-close]' );

		if ( openTrigger ) {
			event.preventDefault();
			openModal();
		}

		if ( closeTrigger ) {
			event.preventDefault();
			closeModal();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && ! modal.hidden ) {
			closeModal();
		}
	} );

	form?.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const formData = new FormData( form );
		formData.append( 'action', 'smarttrak_alarma_callback' );
		formData.append( 'nonce', smarttrakAlarma.nonce );

		try {
			const response = await fetch( smarttrakAlarma.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} );

			const data = await response.json();

			if ( ! response.ok || ! data.success ) {
				throw new Error( data?.data?.message || smarttrakAlarma.error );
			}

			form.reset();
			message.textContent = data.data.message || smarttrakAlarma.success;
			message.hidden = false;
			message.classList.remove( 'is-error' );
			message.classList.add( 'is-success' );
		} catch ( error ) {
			message.textContent = error.message || smarttrakAlarma.error;
			message.hidden = false;
			message.classList.remove( 'is-success' );
			message.classList.add( 'is-error' );
		}
	} );
} )();
