/**
 * Runs `func` at most once every `limit` ms, always letting the trailing call through so the
 * last state (e.g. the final scroll position) is never missed.
 *
 * @param {object} context `this` for `func`
 * @param {function} func
 * @param {number} limit
 * @return {function}
 */
export default function throttle(context, func, limit) {
    let lastFunc;
    let lastRan;

    return (...args) => {
        if (!lastRan) {
            func.apply(context, args);
            lastRan = Date.now();

            return;
        }

        clearTimeout(lastFunc);
        lastFunc = setTimeout(() => {
            if (Date.now() - lastRan >= limit) {
                func.apply(context, args);
                lastRan = Date.now();
            }
        }, limit - (Date.now() - lastRan));
    };
}
