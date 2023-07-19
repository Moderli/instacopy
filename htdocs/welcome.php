<?php
// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include the database connection
include 'db_connect.php';

// Get the post ID and vote type from the URL parameters
$postId = $_GET['postId'];
$voteType = $_GET['voteType'];

// Update the upvote or downvote count based on the vote type
if ($voteType === 'upvote') {
    $query = "UPDATE posts SET upvotes = upvotes + 1 WHERE post_id = ?";
} elseif ($voteType === 'downvote') {
    $query = "UPDATE posts SET downvotes = downvotes + 1 WHERE post_id = ?";
}

$statement = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($statement, 's', $postId);
$result = mysqli_stmt_execute($statement);


// Check if the form is submitted
if (isset($_POST['upload_post'])) {
    $title = $_POST['post_title'];
    $content = $_POST['post_content'];
    $file = $_FILES['associated_file'];
    $fileType = $_POST['file_type'];
    $userId = $_SESSION['user_id'];
    $tags = $_POST['tags'];

    // Call the uploadPost function to handle post upload
    $up_message = uploadPost($title, $content, $file, $fileType, $userId, $tags);
}
// function to get members count
function getMembersCount() {
    global $conn;

    $query = "SELECT COUNT(*) AS count FROM users";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'];

    return $count;
}

// Function to update user status as online
function updateUserStatus($userId, $status)
{
    global $conn;
    $query = "UPDATE users SET status = ? WHERE user_id = ?";
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($statement, 'ss', $status, $userId);
    $result = mysqli_stmt_execute($statement);

    return $result;
}

// Function to retrieve user information from the database
function getUserInformation($userId)
{
    global $conn;
    $query = "SELECT username, UID, img, status FROM users WHERE user_id = ?";
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($statement, 's', $userId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }

    return null;
}

function updateUsername($userId, $newUsername)
{
    global $conn;
    $query = "UPDATE users SET username = ? WHERE user_id = ?";
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($statement, 'ss', $newUsername, $userId);
    $result = mysqli_stmt_execute($statement);

    return $result;
}

// Check if the new username form is submitted
if (isset($_POST['update_username']) && !empty($_POST['new_username'])) {
    $newUsername = $_POST['new_username'];
    $userId = $_SESSION['user_id'];

    // Retrieve the UID from the database
    $uid = getUID($userId);

    if ($uid !== null) {
        // Append the retrieved UID to the new username
        $usernameWithUID = $newUsername . $uid;

        // Call the updateUsername function to update the username with UID
        $result = updateUsername($userId, $usernameWithUID);

        if ($result) {
            // Update the username with UID if the update was successful
            $_SESSION['username'] = $usernameWithUID;
        } else {
            $error = "Failed to update the username.";
        }
    } else {
        $error = "Failed to retrieve the UID from the database.";
    }
}

// Function to retrieve the UID from the database
function getUID($userId)
{
    global $conn;
    $query = "SELECT UID FROM users WHERE user_id = ?";
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($statement, 's', $userId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['UID'];
    }

    return null;
}


// Check if the profile image form is submitted
if (isset($_POST['upload_profile_image'])) {
    $profileImage = $_FILES['profile_image'];

    if ($profileImage['error'] === UPLOAD_ERR_OK) {
        $filename = "images/" . uniqid() . "." . pathinfo($profileImage['name'], PATHINFO_EXTENSION);
        move_uploaded_file($profileImage['tmp_name'], $filename);

        $userId = $_SESSION['user_id'];

        // Update the 'img' column in the database with the image file link
        $query = "UPDATE users SET img = ? WHERE user_id = ?";
        $statement = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($statement, 'ss', $filename, $userId);
        $result = mysqli_stmt_execute($statement);

        if ($result) {
            echo "Profile image uploaded successfully.";
        } else {
            echo "Failed to upload profile image.";
        }

        // Close the statement
        mysqli_stmt_close($statement);
    }
}


// Function to handle post upload
function uploadPost($title, $content, $file, $fileType, $userId, $tags)
{
    global $conn;
    // Handle file upload logic...
    $fileLink = null;
    $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $allowedVideoTypes = ['mp4'];
    $allowedTextTypes = ['txt'];
    $allowedOtherTypes = []; // Add allowed other file types if needed

    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($fileExtension, $allowedImageTypes)) {
            $fileLink = "images/photos/" . uniqid() . "." . $fileExtension;
            move_uploaded_file($file['tmp_name'], $fileLink);
        } elseif (in_array($fileExtension, $allowedVideoTypes)) {
            $fileLink = "images/videos/" . uniqid() . "." . $fileExtension;
            move_uploaded_file($file['tmp_name'], $fileLink);
        } elseif (in_array($fileExtension, $allowedTextTypes)) {
            $fileLink = "images/txt/" . uniqid() . "." . $fileExtension;
            move_uploaded_file($file['tmp_name'], $fileLink);
        } elseif (in_array($fileExtension, $allowedOtherTypes)) {
            $fileLink = "images/other/" . uniqid() . "." . $fileExtension;
            move_uploaded_file($file['tmp_name'], $fileLink);
        }
    }

    // Get the current timestamp
    $timeStamp = date('Y-m-d H:i:s');

    // Insert the post into the database
    $query = "INSERT INTO posts (user_id, post_title, post_content, file_link, file_type, time_stamp, tags) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($statement, 'sssssss', $userId, $title, $content, $fileLink, $fileType, $timeStamp, $tags);
    $result = mysqli_stmt_execute($statement);

    if ($result) {
        $up_message = "Post uploaded successfully.";
    } else {
        $up_message = "Failed to upload post.";
    }

    // Close the statement
    mysqli_stmt_close($statement);

    return $up_message;
}

// Function to add a comment to the comments table
function addComment($commentContent, $commenterId, $postId)
{
    global $conn;
    $query = "INSERT INTO comments (comment_content, commenter_id, post_id, comment_time) VALUES (?, ?, ?, NOW())";
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($statement, 'sss', $commentContent, $commenterId, $postId);
    $result = mysqli_stmt_execute($statement);

    if ($result) {
        echo "Comment added successfully.<br>";
    } else {
        echo "Error adding comment: " . mysqli_error($conn) . "<br>";
    }

    mysqli_stmt_close($statement);
}


// Check if the form is submitted
if (isset($_POST['upload_post'])) {
    $title = $_POST['post_title'];
    $content = $_POST['post_content'];
    $file = $_FILES['associated_file'];
    $fileType = $_POST['file_type'];
    $userId = $_SESSION['user_id'];
    $tags = $_POST['tags'];

    // Call the uploadPost function to handle post upload
    $up_message = uploadPost($title, $content, $file, $fileType, $userId, $tags);
}

// Check if the comment form is submitted
if (isset($_POST['submit_comment'])) {
    $commentContent = $_POST['comment_content'];
    $commenterId = $_SESSION['user_id'];
    $postId = $_POST['post_id'];

    // Call the addComment function to add the comment to the database
    addComment($commentContent, $commenterId, $postId);

// Check if the profile image form is submitted
if (isset($_POST['upload_profile_image'])) {
    $profileImage = $_FILES['profile_image']['tmp_name'];
    $userId = $_SESSION['user_id'];

    // Call the storeProfileImage function to store the profile image
    $result = storeProfileImage($userId, $profileImage);

    if ($result) {
        echo "Profile image uploaded successfully.";
    } else {
        echo "Failed to upload profile image.";
    }
}

}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Font+Name&display=swap">
    <link rel="stylesheet" href="common.css">
    <link rel="icon" type="image/jpg" href="images/icon.jpg">
    
</head>
<body>
<div class="welcome-section">
    <h1>Welcome, <?php echo $_SESSION['username'] . $_SESSION['UID']; ?>!</h1>
    <!-- Rest of the code -->


    <?php if (isset($error)) : ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

<?php if (isset($_SESSION['username']) && isset($_SESSION['UID'])) : ?>
    <p>Username updated to "<?php echo $_SESSION['username'] . $_SESSION['UID']; ?>"</p>
<?php endif; ?>

<?php if (isset($_SESSION['profile_image'])) : ?>
    <img src="<?php echo $_SESSION['profile_image']; ?>" alt="Profile Image">
<?php endif; ?>


    <div class="important">
        <form method="POST" enctype="multipart/form-data">
            <label for="new_username"></label>
            <input type="text" name="new_username" id="new_username" placeholder="New username..." required>
            <input type="submit" name="update_username" value="Update Username">
        </form>

        <form method="POST" enctype="multipart/form-data">
            <label for="profile_image"></label>
            <input type="file" name="profile_image" id="profile_image" required>
            <input type="submit" name="upload_profile_image" value="Upload Profile Image">
        </form>

    </div>
</div>
<!-- Sidebar like Discord -->
<div class="sidebar">
  <div class="sidebar-header">
    <h2>COWENCY</h2>
  </div>
  <a href="profile.php" class="user-profile">
    <?php if (isset($_SESSION['profile_image'])) : ?>
    <img src="<?php echo $_SESSION['profile_image']; ?>" alt="Profile Image">
    <?php endif; ?>
    <p><?php echo $_SESSION['username']; ?></p>
  </a>
  <nav class="sidebar-nav">
    <ul>
      <li><a href="make_prof.php">Make profile</a></li>
      <li><a href="profile.php">Read&write</a></li>
      <li><a href="#">Servers</a></li>
      <li><a href="#">Settings</a></li>
    </ul>
  </nav>
  <div class="more-section">
    <a href="#">More</a>
  </div>
</div>
<?php
mysqli_close($conn);
?>
    <!-- Making posts -->
    <div class="posts">
        <h2>Make a Post</h2>
        <div class="add-post-icon" onclick="togglePostSection()">+</div>
        <div class="post-making-section" id="postSection" style="display: none;">
            <form method="POST" enctype="multipart/form-data">
                <div class="post-form-group">
                    <label for="post_title">Title:</label>
                    <input type="text" name="post_title" id="post_title" placeholder="Enter post title" required>
                </div>
                <div class="post-form-group">
                    <label for="post_content">Content:</label>
                    <textarea name="post_content" id="post_content" rows="4" placeholder="Write your post content" required></textarea>
                </div>
                <div class="post-form-group">
                    <label for="associated_file">Associated File:</label>
                    <input type="file" name="associated_file" id="associated_file" required>
                </div>
                <div class="post-form-group">
                    <label for="file_type">File Type:</label>
                    <select name="file_type" id="file_type" required>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                        <option value="text">Text</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="post-form-group">
                    <label for="tags">Tags:</label>
                    <input type="text" name="tags" id="tags" placeholder="Enter tags">
                </div>
                <input type="submit" name="upload_post" value="Upload Post">
            </form>
            
            <!-- Display success or error message -->
            <?php if (isset($up_message)) : ?>
                <p><?php echo htmlspecialchars($up_message); ?></p>
            <?php endif; ?>
        </div>
    </div>

<div class="posts">
  <h2>Posts</h2>
  <?php
  include 'db_connect.php';

  // Fetch posts from the database
  $query = "SELECT * FROM posts ORDER BY upvotes DESC, time_stamp DESC";
  $result = mysqli_query($conn, $query);

  // Check if there are any posts
  if ($result !== false && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
      $postId = $row['post_id'];
      $title = $row['post_title'];
      $content = $row['post_content'];
      $file = $row['file_link'];
      $upvotes = $row['upvotes'];
      $downvotes = $row['downvotes'];
      $userId = $row['user_id'];

      // Retrieve post owner information
      $postOwner = getUserInformation($userId);

// Display post details
echo "<div class='post'>";
echo "<h3>" . htmlspecialchars($title) . "</h3>";
echo "<p>" . htmlspecialchars($content) . "</p>";
echo "<img src='$file'>";
echo "<div class='post-owner'>";
echo "<div class='post-owner-img-container'><img src='" . $postOwner['img'] . "' alt='Post Owner Avatar' class='post-owner-avatar'></div>";
echo "<div class='post-owner-username'>" . htmlspecialchars($postOwner['username']) . "</div>";
echo "</div>"; // end of post-owner

      echo "<div class='votes'>";
      echo "<span class='upvote'>Upvotes: <span id='upvoteCount-$postId'>$upvotes</span></span>";
      echo "<button onclick='handleUpvote($postId)'>Upvote</button>";
      echo "<span class='downvote'>Downvotes: <span id='downvoteCount-$postId'>$downvotes</span></span>";
      echo "<button onclick='handleDownvote($postId)'>Downvote</button>";
      echo "</div>"; // end of votes
      
      // Comment form for the post
      echo "<form method='POST' enctype='multipart/form-data'>";
      echo "<input type='hidden' name='post_id' value='$postId'>";
      echo "<input type='text' name='comment_content' placeholder='Add a comment...'>";
      echo "<input type='submit' name='submit_comment' value='Comment'>";
      echo "</form>";
      
      // Display comments for the post
      echo "<div class='comments'>";
      // Fetch comments for the post
      $query_comments = "SELECT * FROM comments WHERE post_id = ?";
      $statement_comments = mysqli_prepare($conn, $query_comments);
      mysqli_stmt_bind_param($statement_comments, 's', $postId);
      mysqli_stmt_execute($statement_comments);
      $result_comments = mysqli_stmt_get_result($statement_comments);

      // Check if there are any comments
      if ($result_comments !== false && mysqli_num_rows($result_comments) > 0) {
        while ($row_comments = mysqli_fetch_assoc($result_comments)) {
          $commentId = $row_comments['comment_id'];
          $commentContent = $row_comments['comment_content'];
          $commenterId = $row_comments['commenter_id'];
          $commentTime = $row_comments['comment_time'];

          // Retrieve commenter information
          $commenter = getUserInformation($commenterId);

          // Display comment details
          echo "<div class='comment'>";
          echo "<img src='data:image/jpeg;base64," . base64_encode($commenter['img']) . "' alt='Commenter Avatar' class='comment-avatar'>";
          echo "<div class='comment-content'>";
          echo "<div class='author'>" . htmlspecialchars($commenter['username']) . "</div>";
          echo "<div class='content'>" . htmlspecialchars($commentContent) . "</div>";
          echo "<div class='timestamp'>" . htmlspecialchars($commentTime) . "</div>";
          echo "</div>"; // end of comment-content
          echo "</div>"; // end of comment
        }
      } else {
        echo "<p>No comments found.</p>";
      }

      mysqli_stmt_close($statement_comments);

      echo "</div>"; // end of comments
      
      echo "</div>"; // end of post
    }
  } else {
    echo "<p>No posts found.</p>";
  }

  mysqli_close($conn);
  ?>
</div>


    <!-- JavaScript to toggle post section -->
    <script>
        function togglePostSection() {
            var postSection = document.getElementById("postSection");
            postSection.style.display = postSection.style.display === "none" ? "block" : "none";
        }
    </script>
    <script>
    function handleUpvote(postId) {
        // Send an AJAX request to update the upvote count
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState === 4 && this.status === 200) {
                // Update the upvote count on the page
                var upvoteCount = document.getElementById('upvoteCount-' + postId);
                upvoteCount.innerText = parseInt(upvoteCount.innerText) + 1;
            }
        };
        xhttp.open("GET", "update_votes.php?postId=" + postId + "&voteType=upvote", true);
        xhttp.send();
    }

    function handleDownvote(postId) {
        // Send an AJAX request to update the downvote count
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState === 4 && this.status === 200) {
                // Update the downvote count on the page
                var downvoteCount = document.getElementById('downvoteCount-' + postId);
                downvoteCount.innerText = parseInt(downvoteCount.innerText) + 1;
            }
        };
        xhttp.open("GET", "update_votes.php?postId=" + postId + "&voteType=downvote", true);
        xhttp.send();
    }
</script>

</body>
</html>
