<nav>
    <img class="logo" src="images/logo.png"/>
    <ul>
        <li>
            <a href="index.php">Results</a>
        </li>
        <?php if(isset($_SESSION['user'])): ?>
        <li>
            <a href="add-day.php">Add Race Day</a>
        </li>
        <li>
            <a href="add-comp.php">Add Competitor</a>
        </li>
        <li>
            <a href="logout.php">Logout</a>
        </li>
        <?php else: ?> 
        <li>
            <a href="login.php">Login</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>