/**
 * Reloads the page shortly after midnight if the page shows a perm ride
 * registration in the future.
 *
 * This changes the "Cancel registration" button to "Submit results" so that if
 * people leave the perm registrations summary table open after registering for
 * a perm on a future date, they don't get confused when they return to their
 * computer after the ride and try to submit results.
 * https://rusa.org/bugzilla/show_bug.cgi?id=1085
 */
function initializeReloadMonitor() {
    // Check for a pending perm ride registration in the future. If it doesn't
    // exist then we don't need to reload the page.
    const tables = document.getElementsByTagName('table');
    let found = false;
    for (let i = 0; i < tables.length; i++) {
        if (tables[i].textContent.includes("Cancel registration")) {
            found = true;
            break; 
        }
    }
    if (!found) {
        return;
    }

    const now = new Date();
    const target = new Date();

    // Set the target to 12:01 AM.
    target.setHours(0, 1, 0, 0);

    // If it is already past the target time at the time the script loads,
    // move the target time to tomorrow.
    if (now >= target) {
        target.setDate(target.getDate() + 1);
    }

    // Check every 10 seconds whether the current time is greater than or equal
    // to the target. Reload once that's the case.
    const heartbeat = setInterval(() => {
        const currentTime = new Date();
        if (currentTime >= target) {
            clearInterval(heartbeat);
            window.location.reload();
        }
    }, 10000);
}

initializeReloadMonitor();

