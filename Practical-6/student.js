let students = [];
let currentPage = 1;

const studentsPerPage = 4;


// Fetch student data
fetch("../data/student.json")
    .then(response => response.json())
    .then(data => {
        students = data;
        displayStudents();
    })
    .catch(error => {
        console.log("Error loading student data:", error);

        document.getElementById("studentTable").innerHTML =
            "<tr><td colspan='6'>Unable to load student data.</td></tr>";
    });


// Display students
function displayStudents() {

    let searchText =
        document.getElementById("studentSearch").value.toLowerCase();

    let course =
        document.getElementById("courseFilter").value;

    let sortType =
        document.getElementById("studentSort").value;


    // Search
    let filteredStudents = students.filter(function(student) {

        return student.name.toLowerCase().includes(searchText) ||
               student.enrollment.toLowerCase().includes(searchText);

    });


    // Filter
    if (course !== "All") {

        filteredStudents = filteredStudents.filter(function(student) {
            return student.course === course;
        });

    }


    // Sort by name
    if (sortType === "name") {

        filteredStudents.sort(function(a, b) {
            return a.name.localeCompare(b.name);
        });

    }


    // Sort by attendance
    if (sortType === "attendance") {

        filteredStudents.sort(function(a, b) {
            return b.attendance - a.attendance;
        });

    }


    // Pagination
    let start = (currentPage - 1) * studentsPerPage;
    let end = start + studentsPerPage;

    let pageStudents = filteredStudents.slice(start, end);


    // Display table
    let output = "";

    pageStudents.forEach(function(student) {

        output += `
            <tr>
                <td>${student.enrollment}</td>
                <td>${student.name}</td>
                <td>${student.course}</td>
                <td>${student.year}</td>
                <td>${student.attendance}%</td>
                <td>${student.result}</td>
            </tr>
        `;

    });


    if (pageStudents.length === 0) {

        output =
            "<tr><td colspan='6'>No students found.</td></tr>";

    }


    document.getElementById("studentTable").innerHTML = output;

    document.getElementById("pageNumber").innerText =
        "Page " + currentPage;
}


// Search
document.getElementById("studentSearch")
    .addEventListener("input", function() {

        currentPage = 1;
        displayStudents();

    });


// Filter
document.getElementById("courseFilter")
    .addEventListener("change", function() {

        currentPage = 1;
        displayStudents();

    });


// Sort
document.getElementById("studentSort")
    .addEventListener("change", function() {

        currentPage = 1;
        displayStudents();

    });


// Previous button
document.getElementById("previousBtn")
    .addEventListener("click", function() {

        if (currentPage > 1) {

            currentPage--;
            displayStudents();

        }

    });


// Next button
document.getElementById("nextBtn")
    .addEventListener("click", function() {

        let searchText =
            document.getElementById("studentSearch").value.toLowerCase();

        let course =
            document.getElementById("courseFilter").value;


        let filteredStudents = students.filter(function(student) {

            return student.name.toLowerCase().includes(searchText) ||
                   student.enrollment.toLowerCase().includes(searchText);

        });


        if (course !== "All") {

            filteredStudents = filteredStudents.filter(function(student) {
                return student.course === course;
            });

        }


        let totalPages =
            Math.ceil(filteredStudents.length / studentsPerPage);


        if (currentPage < totalPages) {

            currentPage++;
            displayStudents();

        }

    });