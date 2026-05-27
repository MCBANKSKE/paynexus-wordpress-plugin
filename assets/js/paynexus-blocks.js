( function ( wc, wp ) {
    'use strict';

    var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
    var createElement         = wp.element.createElement;
    var getSetting             = wc.wcSettings.getSetting;
    var decodeEntities        = wp.htmlEntities.decodeEntities;

    var settings = getSetting( 'paynexus_data', {} );

    var label = decodeEntities( settings.title || 'M-Pesa (PayNexus)' );

    /**
     * Label component shown next to the radio button.
     */
    var Label = function () {
        var children = [ createElement( 'span', null, label ) ];

        if ( settings.icon ) {
            children.unshift(
                createElement( 'img', {
                    src: settings.icon,
                    alt: label,
                    style: { display: 'inline', marginRight: '8px', maxHeight: '24px', verticalAlign: 'middle' }
                } )
            );
        }

        return createElement( 'span', { style: { display: 'flex', alignItems: 'center' } }, children );
    };

    /**
     * Content rendered when this gateway is selected.
     */
    var Content = function ( props ) {
        var eventRegistration = props.eventRegistration;
        var emitResponse      = props.emitResponse;

        var phoneRef = wp.element.useRef( '' );

        wp.element.useEffect( function () {
            var unsubscribe = eventRegistration.onPaymentSetup( function () {
                var phone = phoneRef.current;
                if ( ! phone ) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please enter your M-Pesa phone number.'
                    };
                }
                if ( ! /^(?:\+?254|0)\d{9}$/.test( phone ) ) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please enter a valid Kenyan phone number (e.g. 254712345678).'
                    };
                }
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            paynexus_phone: phone
                        }
                    }
                };
            } );
            return unsubscribe;
        }, [ eventRegistration.onPaymentSetup, emitResponse.responseTypes ] );

        return createElement(
            'div',
            null,
            createElement( 'p', null, decodeEntities( settings.description || '' ) ),
            createElement(
                'div',
                { style: { marginTop: '12px' } },
                createElement( 'label', { htmlFor: 'paynexus-phone-block', style: { display: 'block', marginBottom: '4px', fontWeight: 'bold' } }, 'M-Pesa Phone Number *' ),
                createElement( 'input', {
                    type: 'tel',
                    id: 'paynexus-phone-block',
                    placeholder: '254712345678',
                    pattern: '^(?:\\+?254|0)\\d{9}$',
                    required: true,
                    style: { width: '100%', padding: '8px', border: '1px solid #ccc', borderRadius: '4px' },
                    onChange: function ( e ) { phoneRef.current = e.target.value; }
                } ),
                createElement( 'small', { style: { color: '#666' } }, 'Format: 254712345678' )
            )
        );
    };

    registerPaymentMethod( {
        name: 'paynexus',
        label: createElement( Label, null ),
        content: createElement( Content, null ),
        edit: createElement( Content, null ),
        canMakePayment: function () { return true; },
        ariaLabel: label,
        supports: {
            features: settings.supports || [ 'products' ]
        }
    } );

} )( window.wc, window.wp );
