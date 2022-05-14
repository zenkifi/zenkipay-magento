'use strict';

var zenkiPay = (function (baseApiUrl, baseZenkipayUrl, suffixEnvScript) {
    //#region CONSTANTS

    const COUNTRY_API = 'https://ipapi.co/';
    const RESOURCES_URL = 'https://dev-resources.zenki.fi/';
    const ZENKIPAY_ASSETS_URL = ''.concat(
        RESOURCES_URL,
        'zenkipay/script/assets/'
    );

    //#endregion CONSTANTS

    //#region ELEMENT_IDS

    const ROOT_CONTAINER_ID = 'pbw-zenkipay-root-container';
    const MODAL_CONTAINER_ID = 'pbw-zenkipay-modal-container';
    const CLOSE_BTN_CONTAINER_ID = 'pbw-zenkipay-close-button-container';
    const CLOSE_BTN_ID = 'pbw-zenkipay-close-button';
    const IFRAME_CONTAINER_ID = 'pbw-zenkipay-iframe-container';
    const IFRAME_ID = 'pbw-zenkipay-iframe';

    //#endregion ELEMENT_IDS

    //#region MODELS

    const POST_MSG_TYPE = {
        CANCEL: 'cancel',
        DONE: 'done',
        CLOSE: 'close',
        ERROR: 'error',
        HIDE_CLOSE_BTN: 'hideCloseBtn',
    };

    //#endregion MODELS

    return {
        button,
        openModal,
    };

    //#region MAIN

    async function button(containerId, options, callback) {
        if (!(options !== null && options !== void 0 && options.zenkipayKey)) {
            options.zenkipayKey = getZenkipayKey(suffixEnvScript);
        }

        if (!(options !== null && options !== void 0 && options.zenkipayKey)) {
            const error = new Error('Zenkipay key is undefined');

            if (callback) {
                return callback(error, null, {
                    postMsgType: POST_MSG_TYPE.ERROR,
                    isCompleted: true,
                });
            }

            throw error;
        }

        await createButton(
            containerId,
            options,
            () => openModal(options, callback),
            callback
        );
    }

    async function openModal(options, callback) {
        let zenkipayKey =
            options === null || options === void 0 ? void 0 : options.zenkipayKey;

        if (!zenkipayKey) {
            zenkipayKey = getZenkipayKey(suffixEnvScript);
        }

        if (!zenkipayKey) {
            const error = new Error('Zenkipay key is undefined');

            if (callback) {
                return callback(error, null, {
                    postMsgType: POST_MSG_TYPE.ERROR,
                    isCompleted: true,
                });
            }

            throw error;
        }

        try {
            const zenkipayUrl = await getZenkipayUrl(
                zenkipayKey,
                baseApiUrl,
                baseZenkipayUrl,
                options.purchaseData
            );
            await createModal(baseZenkipayUrl, zenkipayUrl, options, callback);
        } catch (error) {
            if (callback) {
                return callback(error, null, {
                    postMsgType: POST_MSG_TYPE.ERROR,
                    isCompleted: true,
                });
            }

            throw error;
        }
    }

    //#endregion MAIN

    //#region FUNCTIONS

    function json2String(input) {
        try {
            if (typeof input === 'string') return input;
            return JSON.stringify(input);
        } catch (_) {
            return input;
        }
    }

    function setShape2Element(element, shape) {
        switch (shape) {
            case 'pill': {
                element.style.borderRadius = '64px';
                break;
            }

            case 'square': {
                element.style.borderRadius = '0';
                break;
            }

            default: {
                element.style.borderRadius = '8px';
            }
        }

        return element;
    }

    //#region ZENKIPAY_KEY

    function getZenkipayKey(suffixEnvScript) {
        const zenkipayKey = getZenkipayKeyFromCurrentScript();
        if (zenkipayKey) return zenkipayKey;
        return getZenkipayKeyUsingSelector(suffixEnvScript);
    }

    function getZenkipayKeyFromCurrentScript() {
        var _document;

        const script =
            (_document = document) === null || _document === void 0
                ? void 0
                : _document.currentScript;
        if (script) return getZenkipayKeyFromScript(script);
        return undefined;
    }

    function getZenkipayKeyUsingSelector(suffixEnvScript) {
        const scriptName = suffixEnvScript
            ? 'zenkipay.'.concat(suffixEnvScript, '.js')
            : 'zenkipay.js';
        const scriptSelector = 'script[src*="'.concat(
            scriptName,
            '?zenkipayKey="]'
        );
        const script = document.querySelector(scriptSelector);
        if (script) return getZenkipayKeyFromScript(script);
        return undefined;
    }

    function getZenkipayKeyFromScript(script) {
        var _script$src;

        const queryParams =
            script === null || script === void 0
                ? void 0
                : (_script$src = script.src) === null || _script$src === void 0
                    ? void 0
                    : _script$src.replace(/^[^?]+\??/, '');
        const { zenkipayKey } = getQueryParams(queryParams);
        return zenkipayKey;
    }

    //#endregion ZENKIPAY_KEY

    //#region QUERY_PARAMS

    function getQueryParams(queryParams) {
        const params = {};
        if (!queryParams) return params;
        const pairs = queryParams.split(/[;&]/);

        for (let i = 0; i < pairs.length; i++) {
            const keyVal = pairs[i].split('=');
            if (!keyVal || keyVal.length != 2) continue;
            const key = decodeURI(keyVal[0]);
            let val = decodeURI(keyVal[1]);
            val = val.replace(/\+/g, ' ');
            params[key] = val;
        }

        return params;
    }

    //#endregion QUERY_PARAMS

    //#endregion FUNCTIONS

    //#region ORCHESTRATORS

    function useHttpAction(zenkipayKey) {
        let baseUrl =
            arguments.length > 1 && arguments[1] !== undefined
                ? arguments[1]
                : baseApiUrl;
        const httpService = useHttpService(baseUrl);

        async function appendAuthorizationInHeaders() {
            let headers =
                arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
            const url = 'public/v1/merchants/plugin/token';
            const options = {
                headers: {
                    'Content-Type': 'text/plain',
                },
            };
            const { token_type, access_token } = await httpService.post(
                url,
                zenkipayKey,
                options
            );
            headers['Authorization'] = ''
                .concat(token_type, ' ')
                .concat(access_token);
            return headers;
        }

        return {
            async get(url) {
                let options =
                    arguments.length > 1 && arguments[1] !== undefined
                        ? arguments[1]
                        : {};
                options.headers = await appendAuthorizationInHeaders(
                    options === null || options === void 0 ? void 0 : options.headers
                );
                return httpService.get(url, options);
            },

            async post(url, body) {
                let options =
                    arguments.length > 2 && arguments[2] !== undefined
                        ? arguments[2]
                        : {};
                options.headers = await appendAuthorizationInHeaders(
                    options === null || options === void 0 ? void 0 : options.headers
                );
                return httpService.post(url, body, options);
            },

            async put(url, body) {
                let options =
                    arguments.length > 2 && arguments[2] !== undefined
                        ? arguments[2]
                        : {};
                options.headers = await appendAuthorizationInHeaders(
                    options === null || options === void 0 ? void 0 : options.headers
                );
                return httpService.put(url, body, options);
            },

            async patch(url, body) {
                let options =
                    arguments.length > 2 && arguments[2] !== undefined
                        ? arguments[2]
                        : {};
                options.headers = await appendAuthorizationInHeaders(
                    options === null || options === void 0 ? void 0 : options.headers
                );
                return httpService.patch(url, body, options);
            },

            async delete(url) {
                let options =
                    arguments.length > 1 && arguments[1] !== undefined
                        ? arguments[1]
                        : {};
                options.headers = await appendAuthorizationInHeaders(
                    options === null || options === void 0 ? void 0 : options.headers
                );
                return httpService.delete(url, options);
            },
        };
    }

    //#region ZENKIPAY_URL_ACTION

    async function getZenkipayUrl(
        zenkipayKey,
        baseApiUrl,
        baseZenkipayUrl,
        purchaseData
    ) {
        try {
            const countryService = useCountryService();
            const country = await countryService.getCountry();
            const merchantService = useMerchantService(zenkipayKey, baseApiUrl);
            const merchant = await merchantService.getMerchant();
            const orderService = useOrderService(zenkipayKey, baseApiUrl);
            const { orderId } = await orderService.postPreOrder(
                merchant,
                purchaseData,
                country
            );
            return ''
                .concat(baseZenkipayUrl, '?zenkipayKey=')
                .concat(zenkipayKey, '&orderId=')
                .concat(orderId);
        } catch (error) {
            return baseZenkipayUrl;
        }
    }

    //#endregion ZENKIPAY_URL_ACTION

    //#region BUTTON_ACTION

    async function createButton(containerId, options, click, callback) {
        const container = getContainer(containerId);
        let button = document.createElement('button');
        button.id = 'pay-with-zenkipay';
        container.appendChild(button);
        const zenkipayImg = createZenkipayImg(button, options);
        setStyles2ZenkipayImg(zenkipayImg, options);
        button = setStyles2Button(button, options);
        return setEvents2Button(button, options, click, callback);
    }

    function getContainer(containerId) {
        if (!containerId) {
            throw new Error('"containerId" is undefined');
        }

        const container = document.getElementById(containerId);

        if (!container) {
            throw new Error("Container with id '".concat(containerId, "' not found"));
        }

        return container;
    }

    async function setEvents2Button(button, options, click, callback) {
        return new Promise((resolve) => {
            button.addEventListener('click', async () => {
                var _options$style;

                switch (
                options === null || options === void 0
                    ? void 0
                    : (_options$style = options.style) === null ||
                        _options$style === void 0
                        ? void 0
                        : _options$style.theme
                ) {
                    case 'dark': {
                        button.style.backgroundColor = '#060606';
                        button.style.borderColor = '#e2e2e2';
                        break;
                    }

                    default: {
                        button.style.backgroundColor = '#f8f8f8';
                        button.style.borderColor = '#e2e2e2';
                    }
                }

                button.style.cursor = 'not-allowed';
                button.disabled = true;
                await click(callback);
                setStyles2Button(button, options);
                button.disabled = false;
                resolve(button);
            });
        });
    }

    function setStyles2Button(button, options) {
        var _options$style2, _options$style3, _options$style4, _options$style5;

        switch (
        options === null || options === void 0
            ? void 0
            : (_options$style2 = options.style) === null ||
                _options$style2 === void 0
                ? void 0
                : _options$style2.theme
        ) {
            case 'dark': {
                button.style.backgroundColor = '#000';
                button.style.borderColor = '#e2e2e2';
                button.style.borderStyle = 'solid';
                button.style.borderWidth = '1px';
                break;
            }

            default: {
                button.style.backgroundColor = '#fff';
                button.style.borderColor = '#e2e2e2';
                button.style.borderStyle = 'solid';
                button.style.borderWidth = '1px';
            }
        }

        switch (
        options === null || options === void 0
            ? void 0
            : (_options$style3 = options.style) === null ||
                _options$style3 === void 0
                ? void 0
                : _options$style3.size
        ) {
            case 'sm': {
                button.style.width = '130px';
                button.style.height = '25px';
                break;
            }

            case 'lg': {
                button.style.width = '300px';
                button.style.height = '50px';
                break;
            }

            default: {
                button.style.width = '196px';
                button.style.height = '38px';
            }
        }

        switch (
        options === null || options === void 0
            ? void 0
            : (_options$style4 = options.style) === null ||
                _options$style4 === void 0
                ? void 0
                : _options$style4.expand
        ) {
            case 'block': {
                button.style.width = '100%';
                break;
            }

            default:
        }

        button.style.cursor = 'pointer';
        return setShape2Element(
            button,
            options === null || options === void 0
                ? void 0
                : (_options$style5 = options.style) === null ||
                    _options$style5 === void 0
                    ? void 0
                    : _options$style5.shape
        );
    }

    function createZenkipayImg(parentContainer, options) {
        var _options$style6;

        const img = document.createElement('img');
        img.style.verticalAlign = 'top';

        switch (
        options === null || options === void 0
            ? void 0
            : (_options$style6 = options.style) === null ||
                _options$style6 === void 0
                ? void 0
                : _options$style6.theme
        ) {
            case 'dark': {
                img.src = ''.concat(ZENKIPAY_ASSETS_URL, 'images/zenkipay-dark.svg');
                break;
            }

            default: {
                img.src = ''.concat(ZENKIPAY_ASSETS_URL, 'images/zenkipay-light.svg');
            }
        }

        parentContainer.appendChild(img);
        return img;
    }

    function setStyles2ZenkipayImg(img, options) {
        var _options$style7;

        switch (
        options === null || options === void 0
            ? void 0
            : (_options$style7 = options.style) === null ||
                _options$style7 === void 0
                ? void 0
                : _options$style7.size
        ) {
            case 'sm': {
                img.style.height = '22px';
                break;
            }

            case 'lg': {
                img.style.height = '46px';
                break;
            }

            default: {
                img.style.height = '34px';
            }
        }

        return img;
    }

    //#endregion BUTTON_ACTION

    //#region MODAL_ACTION

    async function createModal(baseZenkipayUrl, zenkipayUrl, options, callback) {
        return new Promise((resolve) => {
            const rootContainer = createRootContainer();
            const modalContainer = createModalContainer(rootContainer);
            const closeButton = createCloseButton(modalContainer, options);
            createIFrame(modalContainer, zenkipayUrl, options);
            window.addEventListener('message', handleZenkipayMessages);
            closeButton.addEventListener('click', cancelModal);

            function handleZenkipayMessages(_ref) {
                let { data: content, origin } = _ref;
                if (
                    !(
                        baseZenkipayUrl !== null &&
                        baseZenkipayUrl !== void 0 &&
                        baseZenkipayUrl.includes(origin)
                    )
                )
                    return;

                switch (content.type) {
                    case POST_MSG_TYPE.DONE: {
                        if (callback) {
                            callback(null, content.data, {
                                postMsgType: POST_MSG_TYPE.DONE,
                                isCompleted: false,
                            });
                        }

                        hideCloseButton();
                        break;
                    }

                    case POST_MSG_TYPE.CANCEL: {
                        cancelModal();
                        break;
                    }

                    case POST_MSG_TYPE.CLOSE: {
                        if (callback) {
                            callback(null, content.data, {
                                postMsgType: POST_MSG_TYPE.CLOSE,
                                isCompleted: true,
                            });
                        }

                        removeModal();
                        break;
                    }

                    case POST_MSG_TYPE.ERROR: {
                        if (callback) {
                            callback(content.data, null, {
                                postMsgType: POST_MSG_TYPE.ERROR,
                                isCompleted: true,
                            });
                        }

                        removeModal();
                        break;
                    }

                    case POST_MSG_TYPE.HIDE_CLOSE_BTN: {
                        hideCloseButton();
                        break;
                    }
                }
            }

            function hideCloseButton() {
                closeButton.removeEventListener('click', cancelModal);
                const container = closeButton.parentElement;

                if (container) {
                    const parentContainer = container.parentElement;
                    container.removeChild(closeButton);
                    parentContainer && parentContainer.removeChild(container);
                } else {
                    closeButton.style.display = 'none';
                }
            }

            function cancelModal() {
                if (callback) {
                    callback(null, null, {
                        postMsgType: POST_MSG_TYPE.CANCEL,
                        isCompleted: true,
                    });
                }

                removeModal();
            }

            function removeModal() {
                closeButton.removeEventListener('click', cancelModal);
                window.removeEventListener('message', handleZenkipayMessages);
                document.body.removeChild(rootContainer);
                resolve(rootContainer);
            }
        });
    }

    //#region ROOT_CONTAINER_ACTION

    function createRootContainer() {
        let container = document.getElementById(ROOT_CONTAINER_ID);
        container && document.body.removeChild(container);
        container = document.createElement('div');
        container = setStyles2RootContainer(container);
        container.id = ROOT_CONTAINER_ID;
        document.body.appendChild(container);
        return container;
    }

    function setStyles2RootContainer(container) {
        container.style.top = '0';
        container.style.left = '0';
        container.style.width = '100%';
        container.style.height = '100vh';
        container.style.position = 'fixed';
        container.style.alignItems = 'center';
        container.style.flexDirection = 'column';
        container.style.justifyContent = 'center';
        container.style.transition = 'opacity 250ms ease-in-out';
        container.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        container.style.pointerEvents = 'auto';
        container.style.visibility = 'visible';
        container.style.zIndex = '999999';

        container.style.display = 'flex';
        container.style.opacity = '1';
        return container;
    }

    //#endregion ROOT_CONTAINER_ACTION

    //#region MODAL_CONTAINER_ACTION

    function createModalContainer(parentContainer) {
        let container = document.createElement('div');
        container = setStyles2ModalContainer(container);
        container.id = MODAL_CONTAINER_ID;
        parentContainer.appendChild(container);
        return container;
    }

    function setStyles2ModalContainer(container) {
        const maxWidth = 600;
        const height = 750;
        const mediaQueryMaxWidth = maxWidth + 24;
        const smMaxWidth = 'calc(100vw - 48px)';
        const mdMaxWidth = ''.concat(maxWidth, 'px');

        if (window.innerWidth <= mediaQueryMaxWidth) {
            container.style.maxWidth = smMaxWidth;
        } else {
            container.style.maxWidth = mdMaxWidth;
        }

        container.style.width = '100%';
        container.style.height = ''.concat(height, 'px');
        container.style.maxHeight = 'calc(100vh - 48px)';
        container.style.zIndex = '1000000';

        const mediaQuery = window.matchMedia(
            '(max-width: '.concat(mediaQueryMaxWidth, 'px)')
        );
        mediaQuery.addEventListener('change', (event) => {
            if (event.matches) container.style.maxWidth = smMaxWidth;
            else container.style.maxWidth = mdMaxWidth;
        });
        return container;
    }

    //#endregion MODAL_CONTAINER_ACTION

    //#region CLOSE_BUTTON_ACTION

    function createCloseButton(parentContainer, options) {
        const closeButtonContainer = createCloseButtonContainer(parentContainer);
        return prepareCloseButton(closeButtonContainer, options);
    }

    function createCloseButtonContainer(parentContainer) {
        let container = document.createElement('div');
        container = setStyles2CloseButtonContainer(container);
        container.id = CLOSE_BTN_CONTAINER_ID;
        parentContainer.appendChild(container);
        return container;
    }

    function setStyles2CloseButtonContainer(container) {
        container.style.position = 'relative';
        return container;
    }

    function prepareCloseButton(parentContainer, options) {
        let button = document.createElement('button');
        button = setStyles2CloseButton(button, options);
        button.id = CLOSE_BTN_ID;
        createCloseIcon(button);
        parentContainer.appendChild(button);
        return button;
    }

    function createCloseIcon(parentContainer) {
        const image = document.createElement('img');
        image.src = ''.concat(ZENKIPAY_ASSETS_URL, 'icons/icon-close-light.svg');
        image.style.verticalAlign = 'top';
        parentContainer.appendChild(image);
        return image;
    }

    function setStyles2CloseButton(button, options) {
        var _options$style8;

        button.style.padding = '8px';
        button.style.position = 'absolute';
        button.style.backgroundColor = '#efefef';
        button.style.boxShadow = '-1px 1px 4px 1px rgba(0, 0, 0, 0.25)';
        button.style.appearance = 'none';
        button.style.cursor = 'pointer';
        button.style.borderWidth = '0';
        button.style.height = '40px';
        button.style.width = '40px';
        button.style.right = '-16px';
        button.style.top = '-16px';
        return setShape2Element(
            button,
            options === null || options === void 0
                ? void 0
                : (_options$style8 = options.style) === null ||
                    _options$style8 === void 0
                    ? void 0
                    : _options$style8.shape
        );
    }

    //#endregion CLOSE_BUTTON_ACTION

    //#region IFRAME_ACTION

    function createIFrame(parentContainer, zenkipayUrl, options) {
        const iframeContainer = createIFrameContainer(parentContainer);
        return prepareIframe(iframeContainer, zenkipayUrl, options);
    }

    function createIFrameContainer(parentContainer) {
        let container = document.createElement('div');
        container = setStyles2IFrameContainer(container);
        container.id = IFRAME_CONTAINER_ID;
        parentContainer.appendChild(container);
        return container;
    }

    function setStyles2IFrameContainer(container) {
        container.style.height = '100%';
        return container;
    }

    function prepareIframe(container, zenkipayUrl, options) {
        let iframe = document.createElement('iframe');
        if (window.origin && window.origin !== 'null') iframe.name = window.origin;
        iframe = setStyles2Iframe(iframe, options);
        iframe.src = zenkipayUrl;
        iframe.id = IFRAME_ID;
        container.appendChild(iframe);
        return iframe;
    }

    function setStyles2Iframe(iframe, options) {
        var _options$style9;

        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.borderWidth = '0';
        return setShape2Element(
            iframe,
            options === null || options === void 0
                ? void 0
                : (_options$style9 = options.style) === null ||
                    _options$style9 === void 0
                    ? void 0
                    : _options$style9.shape
        );
    }

    //#endregion IFRAME_ACTION

    //#endregion MODAL_ACTION

    //#region SERVICES

    function useHttpService() {
        let baseUrl =
            arguments.length > 0 && arguments[0] !== undefined
                ? arguments[0]
                : baseApiUrl;

        async function request(url, method, options) {
            url = ''.concat(baseUrl).concat(url);
            let body = null;
            let headers = null;

            if (options) {
                if (options !== null && options !== void 0 && options.body)
                    body = json2String(options.body);
                if (options !== null && options !== void 0 && options.headers)
                    headers = options.headers;
            }

            const request = {
                method,
                ...(body && {
                    body,
                }),
                ...(headers && {
                    headers,
                }),
            };

            const response = await fetch(url, request);
            return response.json();
        }

        return {
            async get(url, options) {
                return request(url, 'GET', options);
            },

            async post(url, body, options) {
                const requestOpts = { ...options, body };
                return request(url, 'POST', requestOpts);
            },

            async put(url, body, options) {
                const requestOpts = { ...options, body };
                return request(url, 'PUT', requestOpts);
            },

            async patch(url, body, options) {
                const requestOpts = { ...options, body };
                return request(url, 'PATCH', requestOpts);
            },

            async delete(url, options) {
                return request(url, 'DELETE', options);
            },
        };
    }

    function useCountryService() {
        let baseUrl =
            arguments.length > 0 && arguments[0] !== undefined
                ? arguments[0]
                : COUNTRY_API;
        const http = useHttpService(baseUrl);
        return {
            async getCountry() {
                const url = 'json';
                const { country_code } = await http.get(url);
                return country_code;
            },
        };
    }

    function useMerchantService(zenkipayKey) {
        let baseUrl =
            arguments.length > 1 && arguments[1] !== undefined
                ? arguments[1]
                : baseApiUrl;
        const http = useHttpAction(zenkipayKey, baseUrl);
        return {
            async getMerchant() {
                const url = 'v1/merchants/plugin';
                const headers = {
                    'Content-Type': 'text/plain',
                };
                return http.post(url, zenkipayKey, {
                    headers,
                });
            },
        };
    }

    function useOrderService(zenkipayKey) {
        let baseUrl =
            arguments.length > 1 && arguments[1] !== undefined
                ? arguments[1]
                : baseApiUrl;
        const http = useHttpAction(zenkipayKey, baseUrl);
        return {
            async postPreOrder(merchant, purchaseData, country) {
                const url = 'v1/orders/pre';
                const preOrder = {
                    orderPlacedAt: new Date(),
                    merchantId: merchant.merchantId,
                    merchantName: merchant.merchantName,
                    merchantUnitId: merchant.merchantUnitId,
                    country: country || purchaseData.country || '',
                    totalAmount: purchaseData.amount,
                    currency: purchaseData.currency,
                    items: purchaseData.items,
                };
                const options = {
                    headers: {
                        'Content-Type': 'application/json',
                    },
                };
                return http.post(url, preOrder, options);
            },
        };
    }

    //#endregion SERVICES

    //#endregion ORCHESTRATORS
})('https://dev-gateway.zenki.fi/', 'https://payments-dev.zenki.fi/#/', '');