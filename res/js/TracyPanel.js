/**
 * Wireframe Tracy Panel
 *
 * @version 0.1.1
 */
class WireframeTracyPanel {

    /**
     * Constructor
     */
    constructor() {
        this.initCache();
        this.initTabs();
        this.initAPI();
    }

    /**
     * Init cache
     */
    initCache() {
        this.cache = {
            prefix: "wireframe-tracy-",
            jsPrefix: "js-wireframe-tracy-",
        };
        this.cache.api = {
            form: document.getElementById(this.cache.jsPrefix + "api-form"),
            params: Array.from(document.querySelectorAll("." + this.cache.jsPrefix + "api-param")),
            endpoint: document.getElementById(this.cache.jsPrefix + "api-endpoint"),
            args: document.getElementById(this.cache.jsPrefix + "api-api_args"),
            query: document.getElementById(this.cache.jsPrefix + "api-query"),
            submit: document.getElementById(this.cache.jsPrefix + "api-submit"),
            response: document.getElementById(this.cache.jsPrefix + "api-response"),
        };
    }

    /**
     * Init tabs
     */
    initTabs() {
        const tabs = document.querySelectorAll("." + this.cache.prefix + "tab");
        if (!tabs.length) return;
        const tablinks = document.querySelectorAll("." + this.cache.prefix + "tab-link");
        tablinks.forEach(link => {
            link.addEventListener("click", event => {
                event.preventDefault();
                tabs.forEach(tab => {
                    tab.setAttribute("hidden", true);
                });
                tablinks.forEach(inactiveLink => {
                    inactiveLink.classList.remove(this.cache.prefix + "tab-link--current");
                });
                link.classList.add(this.cache.prefix + "tab-link--current");
                document.getElementById(link.getAttribute("href").substr(1)).removeAttribute("hidden");
            });
        });
    }

    /**
     * Init API debugger
     */
    initAPI() {

        // bail out early if no endpoints were found
        if (!this.cache.api.endpoint || !this.cache.api.endpoint.options.length) {
            return;
        }

        // display endpoint specific inputs
        this.toggleAPIFormInputs(this.cache.api.endpoint.options[0].value);
        this.cache.api.endpoint.addEventListener("change", event => {
            const endpoint = event.target.selectedOptions[0].value;
            this.toggleAPIFormInputs(endpoint);
        });

        // set up API query based on selected params
        if (this.cache.api.params.length) {
            this.cache.api.params.forEach(param => {
                param.addEventListener("change", () => {
                    this.populateAPIQuery();
                });
                param.addEventListener("keyup", () => {
                    this.populateAPIQuery();
                });
            });
            this.cache.api.args.addEventListener("change", () => {
                this.populateAPIQuery();
            });
            this.cache.api.args.addEventListener("keyup", () => {
                this.populateAPIQuery();
            });
        }

        // perform API query when form is submitted
        this.cache.api.form.addEventListener("submit", event => {
            event.preventDefault();
            this.sendAPIQuery();
        });
    }

    /**
     * Get args for API query
     *
     * @returns {String}
     */
    getAPIQueryArgs() {
        let args = this.cache.api.args.value;
        if (args === "") return "";
        try {
            args = JSON.stringify(JSON.parse(args));
            return "api_args=" + encodeURIComponent(args);
        } catch (e) {
            return args;
        }
    }

    /**
     * Show applicable inputs
     *
     * @param {String} endpoint
     */
    toggleAPIFormInputs(endpoint) {
        document.querySelectorAll("." + this.cache.prefix + "api-form-row--endpoint").forEach(option => {
            option.setAttribute("hidden", true);
        });
        const endpointOptions = document.querySelectorAll("." + this.cache.prefix + "api-form-row--endpoint-" + endpoint);
        if (endpointOptions.length) {
            endpointOptions.forEach(option => {
                option.removeAttribute("hidden");
            });
        }
        this.populateAPIQuery();
    }

    /**
     * Populate API query into the container element
     */
    populateAPIQuery() {
        let query = "";
        this.cache.api.params.some(param => {
            let value = param.value;
            if (param.name === "api_root" && !value.match(/^https?\:\/\//)) {
                value = "/" + value.replace(/^\/+|\/+$/g, "") + "/";
                if (value === "//") {
                    // API root not specified, make sure that query is empty and bail out early
                    query = "";
                    return true;
                }
            } else if (param.parentNode.getAttribute("hidden") || value === "") {
                // field is hidden or value is empty, skip
                return false;
            } else if (param.name === "endpoint") {
                value += "/";
            } else if (param.name === "return_format") {
                query += "/";
            }
            query += value;
            if (param.name === "api_root" && !value.match(/\/$|\?|\&/)) {
                query += "/";
            }
        });
        if (query !== "") {
            query += (query.match(/\?/) ? "&" : "?") + this.getAPIQueryArgs();
        }
        this.cache.api.query.innerText = query;
        if (query === "") {
            this.cache.api.submit.setAttribute("disabled", true);
            return;
        }
        this.cache.api.submit.removeAttribute("disabled");
    }

    /**
     * Send API query
     */
    sendAPIQuery() {
        this.cache.api.submit.setAttribute("disabled", true);
        const xhr = new XMLHttpRequest();
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                this.cache.api.response.classList.remove(this.cache.prefix + "api-code--error");
            } else {
                this.cache.api.response.classList.add(this.cache.prefix + "api-code--error");
            }
            try {
                const response = JSON.parse(xhr.response);
                this.cache.api.response.innerText = JSON.stringify(response, null, 2);
            } catch (e) {
                this.cache.api.response.innerText = xhr.response;
            }
            this.cache.api.response.removeAttribute("hidden");
            this.cache.api.response.focus();
            this.cache.api.submit.removeAttribute("disabled");
        };
        let request = this.cache.api.query.innerText;
        if (request === "") {
            this.cache.api.response.classList.add(this.cache.prefix + "api-code--error");
            this.cache.api.response.innerText = "Empty request, unable to proceed.";
            this.cache.api.response.removeAttribute("hidden");
            this.cache.api.response.focus();
            return;
        }
        request += (request.match(/\?/) ? "&" : "?") + this.getAPIQueryArgs();
        xhr.open("GET", request);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    }
}

new WireframeTracyPanel();
