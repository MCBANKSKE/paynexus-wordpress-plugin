( function ( wc, wp ) {
    'use strict';

    var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
    var createElement         = wp.element.createElement;
    var getSetting             = wc.wcSettings.getSetting;
    var decodeEntities        = wp.htmlEntities.decodeEntities;
    var useState              = wp.element.useState;

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
        var validState = useState( '' );
        var validClass = validState[0];
        var setValidClass = validState[1];

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

        var onPhoneChange = function ( e ) {
            var v = e.target.value.replace( /\D/g, '' );
            phoneRef.current = e.target.value;
            if ( v.length === 0 ) {
                setValidClass( '' );
            } else if ( /^(?:254\d{9}|0\d{9}|\d{9})$/.test( v ) ) {
                setValidClass( 'valid' );
            } else {
                setValidClass( 'invalid' );
            }
        };

        var feedbackColor = validClass === 'valid' ? '#00A650' : ( validClass === 'invalid' ? '#e53935' : 'transparent' );

        return createElement(
            'div',
            { style: { border: '1px solid #e0e0e0', borderRadius: '8px', padding: '16px', background: '#fafffe' } },
            createElement( 'p', { style: { margin: '0 0 12px', color: '#555', fontSize: '14px' } }, decodeEntities( settings.description || '' ) ),
            createElement(
                'div',
                null,
                createElement(
                    'label',
                    { htmlFor: 'paynexus-phone-block', style: { display: 'flex', alignItems: 'center', marginBottom: '8px', fontWeight: '600', fontSize: '14px', color: '#333' } },
                    createElement( 'svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: '#00A650', strokeWidth: 2, style: { marginRight: '6px' } },
                        createElement( 'rect', { x: 5, y: 2, width: 14, height: 20, rx: 2, ry: 2 } ),
                        createElement( 'line', { x1: 12, y1: 18, x2: 12.01, y2: 18 } )
                    ),
                    'M-Pesa Phone Number *'
                ),
                createElement(
                    'div',
                    { style: { display: 'flex', alignItems: 'center', border: '1px solid #ccc', borderRadius: '6px', overflow: 'hidden', transition: 'border-color 0.2s' } },
                    createElement( 'span', { style: { padding: '10px 12px', background: '#f5f5f5', color: '#333', fontWeight: '600', fontSize: '14px', borderRight: '1px solid #e0e0e0', whiteSpace: 'nowrap' } }, '+254' ),
                    createElement( 'input', {
                        type: 'tel',
                        id: 'paynexus-phone-block',
                        placeholder: '712345678',
                        pattern: '^(?:\\+?254|0)\\d{9}$',
                        required: true,
                        style: { flex: 1, padding: '10px 12px', border: 'none', fontSize: '15px', outline: 'none', boxShadow: 'none' },
                        onChange: onPhoneChange
                    } )
                ),
                createElement( 'div', { style: { height: '3px', borderRadius: '2px', marginTop: '4px', background: feedbackColor, transition: 'background 0.3s' } } ),
                createElement( 'small', { style: { color: '#888', fontSize: '12px', marginTop: '4px', display: 'block' } }, 'Format: 254712345678 or 0712345678' )
            ),
            createElement(
                'div',
                { style: { display: 'flex', alignItems: 'center', gap: '4px', marginTop: '12px', fontSize: '11px', color: '#00A650' } },
                createElement( 'svg', { width: 12, height: 12, viewBox: '0 0 24 24', fill: 'none', stroke: '#00A650', strokeWidth: 2 },
                    createElement( 'rect', { x: 3, y: 11, width: 18, height: 11, rx: 2, ry: 2 } ),
                    createElement( 'path', { d: 'M7 11V7a5 5 0 0 1 10 0v4' } )
                ),
                'Secured by PayNexus'
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
