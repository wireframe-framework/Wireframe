/**
 * Wireframe Tracy Panel
 *
 * @version 0.1.0
 */
class WireframeTracyPanel {

    /**
     * Constructor
     */
    constructor() {
        this.initTabs();
        this.initAPI();
    }

    /**
     * Init tabs
     */
    initTabs() {
        const tracyTabs = document.querySelectorAll(".wireframe-tracy-tab");
        if (!tracyTabs.length) return;
        const tablinks = document.querySelectorAll(".wireframe-tracy-tab-link");
        tablinks.forEach(function(link) {
            link.addEventListener("click", function(event) {
                event.preventDefault();
                tracyTabs.forEach(function(tab) {
                    tab.setAttribute("hidden", "");
                });
                tablinks.forEach(function(inactiveLink) {
                    inactiveLink.classList.remove("wireframe-tracy-tab-link--current");
                });
                link.classList.add("wireframe-tracy-tab-link--current");
                document.getElementById(link.getAttribute("href").substr(1)).removeAttribute("hidden");
            });
        });
    }

    /**
     * Init API debugger
     */
    initAPI() {
        const endpointSelect = document.getElementById("js-wireframe-tracy-api-endpoint");
        if (!endpointSelect || !endpointSelect.options.length) return;
        this.showInputs(endpointSelect.options[0].value);
        endpointSelect.addEventListener("change", event => {
            const endpoint = event.target.selectedOptions[0].value;
            this.showInputs(endpoint);
        });
        const APIParams = document.querySelectorAll(".js-wireframe-tracy-api-param");
        if (APIParams.length) {
            APIParams.forEach(param => {
                param.addEventListener("change", () => {
                    this.populateAPIQuery();
                });
                param.addEventListener("keyup", () => {
                    this.populateAPIQuery();
                });
            });
            const APIArgs = document.querySelector(".js-wireframe-tracy-api-args");
            APIArgs.addEventListener("change", () => {
                this.populateAPIQuery();
            });
            APIArgs.addEventListener("keyup", () => {
                this.populateAPIQuery();
            });
        }
        const APIForm = document.getElementById("js-wireframe-tracy-api-form");
        APIForm.addEventListener("submit", event => {
            event.preventDefault();
            const xhr = new XMLHttpRequest();
            xhr.onload = function() {
                const responseContainer = document.getElementById("js-wireframe-tracy-api-response");
                if (xhr.status >= 200 && xhr.status < 300) {
                    responseContainer.classList.remove("wireframe-tracy-api-code--error");
                } else {
                    responseContainer.classList.add("wireframe-tracy-api-code--error");
                }
                try {
                    const response = JSON.parse(xhr.response);
                    responseContainer.innerText = JSON.stringify(response, null, 2);
                } catch (e) {
                    responseContainer.innerText = xhr.response;
                }
                responseContainer.removeAttribute("hidden");
                responseContainer.focus();
            };
            const APIRequest = document.getElementById("js-wireframe-tracy-api-query").innerText + this.getAPIArgs();
            xhr.open("GET", APIRequest);
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            xhr.send();
        });
    }

    /**
     * Get API args
     *
     * @return {String}
     */
    getAPIArgs() {
        let APIArgs = document.querySelector(".js-wireframe-tracy-api-args").value;
        if (APIArgs == "") return "";
        try {
            APIArgs = JSON.stringify(JSON.parse(APIArgs));
            return "&api_args=" + encodeURIComponent(APIArgs);
        } catch (e) {
            return "&" + APIArgs;
        }
    }

    /**
     * Show applicable inputs
     *
     * @param {String} endpoint
     */
    showInputs(endpoint) {
        document.querySelectorAll(".wireframe-tracy-api-form-row--endpoint").forEach(option => {
            option.setAttribute("hidden", true);
        });
        const endpointOptions = document.querySelectorAll(".wireframe-tracy-api-form-row--endpoint-" + endpoint);
        if (endpointOptions.length) {
            endpointOptions.forEach(option => {
                option.removeAttribute("hidden");
            });
        }
        this.populateAPIQuery();
    }

    /**
     * Populate API Query element
     */
    populateAPIQuery() {
        const APIQueryElement = document.getElementById("js-wireframe-tracy-api-query");
        const APIParams = document.querySelectorAll(".js-wireframe-tracy-api-param");
        let APIQuery = "";
        APIParams.forEach(APIParam => {
            if (APIParam.parentNode.getAttribute("hidden") || APIParam.value == "") return;
            if (["api_root", "endpoint"].indexOf(APIParam.name) === -1) {
                APIQuery += "/";
            }
            APIQuery += APIParam.value;
            if (APIParam.name === "api_root" && !APIParam.value.match(/\/$|\?|\&/)) {
                APIQuery += "/";
            }
        });
        APIQuery += this.getAPIArgs();
        APIQueryElement.innerText = APIQuery;
    }
}

new WireframeTracyPanel();
