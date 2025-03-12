import axios from "axios";
import lodash from "lodash";

window._ = lodash;
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
