(() => {
    const page = document.querySelector(".admin-events");
    const form = document.querySelector("[data-event-form]");
    const eventList = document.querySelector("[data-event-list]");
    const notice = document.querySelector("[data-event-notice]");
    const totalCount = document.querySelector("[data-event-count]");
    const formHeading = document.querySelector("[data-form-heading]");
    const submitButton = form?.querySelector('button[type="submit"]');

    if (!page || !form || !eventList || !notice || !totalCount || !formHeading || !submitButton) {
        return;
    }

    const apiUrl = page.dataset.apiUrl;
    const csrfToken = page.dataset.csrfToken;
    const fields = {
        id: form.elements.namedItem("event_id"),
        action: form.elements.namedItem("action"),
        name: form.elements.namedItem("event_name"),
        date: form.elements.namedItem("event_date"),
        description: form.elements.namedItem("description"),
        poster: form.elements.namedItem("poster")
    };
    let events = [];

    function showNotice(message, isError = false) {
        notice.textContent = message;
        notice.classList.toggle("error", isError);
        notice.classList.toggle("success", !isError);
        notice.hidden = false;
    }

    async function requestApi(options = {}) {
        const response = await fetch(apiUrl, {
            method: options.method || "GET",
            headers: {
                "X-CSRF-Token": csrfToken,
                ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" })
            },
            body: options.body instanceof FormData
                ? options.body
                : (options.body ? JSON.stringify(options.body) : undefined),
            credentials: "same-origin"
        });

        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error("The server returned an unreadable response. Please refresh and try again.");
        }
        if (!response.ok || !result.success) {
            throw new Error(result.message || "The request could not be completed.");
        }
        return result;
    }

    function appendCell(row, content, className = "") {
        const cell = document.createElement("td");
        if (className) {
            cell.className = className;
        }
        if (content instanceof Node) {
            cell.append(content);
        } else {
            cell.textContent = content;
        }
        row.append(cell);
        return cell;
    }

    function renderEvents() {
        eventList.replaceChildren();
        totalCount.textContent = `${events.length} total`;
        const countNumber = page.querySelector(".event-count strong");
        const countLabel = page.querySelector(".event-count span");
        if (countNumber) {
            countNumber.textContent = String(events.length);
        }
        if (countLabel) {
            countLabel.textContent = events.length === 1 ? "event listed" : "events listed";
        }

        if (events.length === 0) {
            const row = document.createElement("tr");
            const cell = appendCell(row, "", "");
            cell.colSpan = 5;
            const empty = document.createElement("div");
            empty.className = "event-empty-state";
            const title = document.createElement("strong");
            title.textContent = "No events yet";
            const description = document.createElement("span");
            description.textContent = "Create your first event using the form above.";
            empty.append(title, description);
            cell.append(empty);
            eventList.append(row);
            return;
        }

        events.forEach(event => {
            const row = document.createElement("tr");
            const posterCell = document.createElement("td");
            if (event.poster_path) {
                const poster = document.createElement("img");
                poster.className = "event-thumbnail";
                poster.src = event.poster_path;
                poster.alt = `Poster for ${event.event_name}`;
                posterCell.append(poster);
            } else {
                const noPoster = document.createElement("span");
                noPoster.className = "event-no-poster";
                noPoster.textContent = "No poster";
                posterCell.append(noPoster);
            }
            row.append(posterCell);

            const titleCell = appendCell(row, "", "event-title-cell");
            const title = document.createElement("strong");
            title.textContent = event.event_name;
            const id = document.createElement("span");
            id.textContent = `Event #${event.event_id}`;
            titleCell.append(title, id);

            const dateCell = appendCell(row, "", "event-date-cell");
            const date = document.createElement("time");
            date.dateTime = event.event_date;
            const parsedDate = new Date(`${event.event_date}T00:00:00`);
            date.textContent = Number.isNaN(parsedDate.getTime())
                ? event.event_date
                : parsedDate.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
            dateCell.append(date);

            appendCell(row, event.description || "", "event-description-cell");

            const actions = appendCell(row, "", "event-actions");
            const edit = document.createElement("a");
            edit.className = "event-edit";
            edit.href = `#event-${event.event_id}`;
            edit.dataset.editEvent = String(event.event_id);
            edit.textContent = "Edit";
            actions.append(edit);

            const deleteForm = document.createElement("form");
            deleteForm.dataset.deleteEvent = String(event.event_id);
            const deleteButton = document.createElement("button");
            deleteButton.className = "event-delete";
            deleteButton.type = "submit";
            deleteButton.textContent = "Delete";
            deleteForm.append(deleteButton);
            actions.append(deleteForm);
            eventList.append(row);
        });
    }

    function setEditMode(event = null) {
        const isEditing = event !== null;
        fields.id.value = isEditing ? String(event.event_id) : "";
        fields.action.value = isEditing ? "update" : "create";
        fields.name.value = isEditing ? event.event_name : "";
        fields.date.value = isEditing ? event.event_date : "";
        fields.description.value = isEditing ? (event.description || "") : "";
        fields.poster.required = !isEditing;
        formHeading.textContent = isEditing ? "Edit event" : "Create an event";
        submitButton.textContent = isEditing ? "Save changes" : "Create event";
        page.querySelector(".event-section-label").textContent = isEditing ? "UPDATE DETAILS" : "GET STARTED";

        let cancel = form.closest(".event-editor").querySelector("[data-cancel-edit]");
        if (isEditing && !cancel) {
            cancel = document.createElement("button");
            cancel.type = "button";
            cancel.className = "event-cancel-link";
            cancel.dataset.cancelEdit = "";
            cancel.textContent = "Cancel editing";
            form.closest(".event-editor").querySelector(".event-panel-heading").append(cancel);
        } else if (!isEditing && cancel) {
            cancel.remove();
        }

        const preview = form.querySelector(".event-current-poster");
        if (preview) {
            preview.remove();
        }
        const uploadRow = form.querySelector(".event-upload-row");
        if (isEditing && event.poster_path) {
            const currentPoster = document.createElement("div");
            currentPoster.className = "event-current-poster";
            const label = document.createElement("span");
            label.className = "event-upload-title";
            label.textContent = "Current poster";
            const image = document.createElement("img");
            image.src = event.poster_path;
            image.alt = `Current poster for ${event.event_name}`;
            currentPoster.append(label, image);
            uploadRow.prepend(currentPoster);
        }
        const uploadLabel = uploadRow.querySelector(".event-upload");
        uploadLabel.querySelector(".event-upload-title").textContent =
            isEditing ? "Event poster (optional)" : "Event poster *";
        uploadLabel.querySelectorAll("[data-edit-help]").forEach(element => element.remove());
        if (isEditing) {
            const help = document.createElement("small");
            help.dataset.editHelp = "";
            help.textContent = "Choose a new image only if you want to replace the current poster.";
            uploadLabel.append(help);
        }
        form.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    async function loadEvents() {
        eventList.setAttribute("aria-busy", "true");
        try {
            const result = await requestApi();
            events = result.events;
            renderEvents();
            const initialEdit = events.find(item => item.event_id === Number(fields.id.value));
            if (initialEdit) {
                setEditMode(initialEdit);
            } else {
                setEditMode();
            }
        } catch (error) {
            showNotice(error.message, true);
            const row = document.createElement("tr");
            const cell = appendCell(row, error.message);
            cell.colSpan = 5;
            eventList.replaceChildren(row);
        } finally {
            eventList.removeAttribute("aria-busy");
        }
    }

    form.addEventListener("submit", async event => {
        event.preventDefault();
        submitButton.disabled = true;
        submitButton.textContent = "Saving...";
        const formData = new FormData(form);
        try {
            const result = await requestApi({ method: "POST", body: formData });
            events = result.events;
            renderEvents();
            form.reset();
            setEditMode();
            showNotice(result.message);
        } catch (error) {
            showNotice(error.message, true);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = fields.action.value === "update" ? "Save changes" : "Create event";
        }
    });

    eventList.addEventListener("click", event => {
        const editLink = event.target.closest("[data-edit-event]");
        if (!editLink) {
            return;
        }
        event.preventDefault();
        const selectedEvent = events.find(item => item.event_id === Number(editLink.dataset.editEvent));
        if (selectedEvent) {
            setEditMode(selectedEvent);
        }
    });

    eventList.addEventListener("submit", async event => {
        const deleteForm = event.target.closest("[data-delete-event]");
        if (!deleteForm) {
            return;
        }
        event.preventDefault();
        if (!window.confirm("Delete this event?")) {
            return;
        }
        const button = deleteForm.querySelector("button");
        button.disabled = true;
        try {
            const result = await requestApi({
                method: "POST",
                body: {
                    action: "delete",
                    event_id: deleteForm.dataset.deleteEvent
                }
            });
            events = result.events;
            renderEvents();
            showNotice(result.message);
            if (fields.id.value === deleteForm.dataset.deleteEvent) {
                form.reset();
                setEditMode();
            }
        } catch (error) {
            button.disabled = false;
            showNotice(error.message, true);
        }
    });

    form.closest(".event-editor").addEventListener("click", event => {
        if (event.target.closest("[data-cancel-edit]")) {
            form.reset();
            setEditMode();
        }
    });

    loadEvents();
})();
