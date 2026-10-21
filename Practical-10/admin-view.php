<?php
require_once __DIR__ . "/../auth.php";
requireRole("admin");
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub Portal</title>

    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    
     <header class="header">   
     <nav class="navbar">

        <div class="logo">
            <img src="https://img.icons8.com/bubbles/1200/charotar-university.jpg"
                 alt="University Logo" 
                 width="50"
                 height="50">
            <h2>Student Hub</h2>
        </div>

     </nav>
     <nav class="navbar">

        <div class="nav">
            <a href="admin-dashboard.php" class="nav-btn">Dashboard</a>

        </div>

    </nav>

</header>
<center>

    <h2>Student Records</h2>

    <!-- Search, Filter and Sort -->
    <div class="student-controls">

        <input
            type="text"
            id="studentSearch"
            placeholder="Search student..."
        >

        <select id="courseFilter">
            <option value="All">All Courses</option>
            <option value="DCS">DCS</option>
            <option value="BCA">BCA</option>
            <option value="B.Tech">B.Tech</option>
        </select>

        <select id="studentSort">
            <option value="none">Sort By</option>
            <option value="name">Name</option>
            <option value="attendance">Attendance</option>
        </select>

    </div>


    <!-- Student Table -->
    <table border="1" cellpadding="10">

        <thead>
            <tr>
                <th>Enrollment</th>
                <th>Name</th>
                <th>Course</th>
                <th>Year</th>
                <th>Attendance</th>
                <th>Result</th>
            </tr>
        </thead>

        <tbody id="studentTable">

            <!-- Student data will come from JSON -->

        </tbody>

    </table>


    <!-- Pagination -->
    <div class="pagination">

        <button id="previousBtn">Previous</button>

        <span id="pageNumber">Page 1</span>

        <button id="nextBtn">Next</button>

    </div>

</center>
<footer>
    <p>© 2026 Student Hub Portal</p>
</footer>

<script src="../js/script.js"></script>
<script src="../js/student.js"></script>

</body>
</html>