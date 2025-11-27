<?php

include 'config.php';
session_start();

// page redirect
$usermail="";
$usermail=$_SESSION['usermail'];
if($usermail == true){

}else{
  header("location: index.php");
}

// Get logged-in user details with error handling
$loggedInEmail = $usermail;

// Get vacant rooms count per room type with error handling
function getVacantRooms($conn, $type) {
    // Get total available rooms for this type
    $totalQ = mysqli_query($conn, "SELECT numrooms AS total FROM room WHERE type='$type'");
    $total = 0;
    if ($totalQ && mysqli_num_rows($totalQ) > 0) {
        $totalRow = mysqli_fetch_assoc($totalQ);
        $total = $totalRow['total'] ?? 0;
    }
    
    // Count ONLY active booked rooms for this type (exclude CheckedOut)
    $bookedQ = mysqli_query($conn, "SELECT COUNT(*) AS booked FROM roombook WHERE RoomType='$type' AND stat != 'CheckedOut'");
    $booked = 0;
    if ($bookedQ) {
        $bookedRow = mysqli_fetch_assoc($bookedQ);
        $booked = $bookedRow['booked'] ?? 0;
    }
    
    // Calculate vacant rooms
    $vacant = $total - $booked;
    return max(0, $vacant); // Ensure non-negative
}

$vacantSuperior = getVacantRooms($conn, "Superior Room");
$vacantDeluxe = getVacantRooms($conn, "Deluxe Room");
$vacantGuestHouse = getVacantRooms($conn, "Guest House");
$vacantSingle = getVacantRooms($conn, "Single Room");

$reviewAlertMessage = '';
$reviewAlertIcon = 'success';

if (isset($_POST['submit_review'])) {
    $reviewName = mysqli_real_escape_string($conn, trim($_POST['review_name'] ?? ''));
    $reviewRoomType = mysqli_real_escape_string($conn, trim($_POST['review_room_type'] ?? ''));
    $reviewRating = (int) ($_POST['review_rating'] ?? 0);
    $reviewComment = mysqli_real_escape_string($conn, trim($_POST['review_comment'] ?? ''));

    if ($reviewName === '' || $reviewRoomType === '' || $reviewComment === '' || $reviewRating < 1 || $reviewRating > 5) {
        $_SESSION['review_message'] = 'Please fill in all review fields and select a rating between 1 and 5.';
        $_SESSION['review_icon'] = 'error';
    } else {
        $insertReviewSql = "INSERT INTO customer_reviews (name, room_type, rating, comment, status, created_at) VALUES ('$reviewName', '$reviewRoomType', '$reviewRating', '$reviewComment', 'approved', NOW())";
        if (mysqli_query($conn, $insertReviewSql)) {
            $_SESSION['review_message'] = 'Thank you for sharing your experience!';
            $_SESSION['review_icon'] = 'success';
        } else {
            $_SESSION['review_message'] = 'Could not save your review right now. Please try again.';
            $_SESSION['review_icon'] = 'error';
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "#reviews");
    exit();
}

// Handle room booking form submission
if (isset($_POST['guestdetailsubmit'])) {
    $Name = mysqli_real_escape_string($conn, trim($_POST['Name']));
    $Email = mysqli_real_escape_string($conn, trim($_POST['Email']));
    $Phone = mysqli_real_escape_string($conn, trim($_POST['Phone']));
    $RoomType = mysqli_real_escape_string($conn, trim($_POST['RoomType']));
    $cin = $_POST['cin'];
    $cout = $_POST['cout'];

    // Validate name contains only letters and spaces
    if (!preg_match("/^[a-zA-Z\s]+$/", $Name)) {
        $_SESSION['booking_message'] = "Invalid Name - Name should contain only letters and spaces";
        $_SESSION['booking_icon'] = "error";
    }
    // Validate email format
    else if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['booking_message'] = "Invalid Email - Please enter a valid email address";
        $_SESSION['booking_icon'] = "error";
    }
    // Validate phone contains only numbers and allowed characters
    else if (!preg_match("/^[0-9+\-\s()]+$/", $Phone)) {
        $_SESSION['booking_message'] = "Invalid Phone Number - Phone number should contain only numbers";
        $_SESSION['booking_icon'] = "error";
    }
    else if($Name == "" || $Email == "" || $Phone == "" || $RoomType == "" || $cin == "" || $cout == ""){
        $_SESSION['booking_message'] = "Fill all required details";
        $_SESSION['booking_icon'] = "error";
    }
    else{
        // Check if customer already has an active booking for this room type
        $checkExistingBooking = mysqli_query($conn, "
            SELECT id FROM roombook 
            WHERE Email = '$Email' 
            AND RoomType = '$RoomType' 
            AND stat IN ('NotConfirm', 'Confirm')
        ");
        
        if ($checkExistingBooking && mysqli_num_rows($checkExistingBooking) > 0) {
            $_SESSION['booking_message'] = "Duplicate Booking Not Allowed - You already have an active booking for this room type. Please check your bookings or choose a different room type.";
            $_SESSION['booking_icon'] = "warning";
        } else {
            // Check if room is available (exclude CheckedOut bookings)
            $checkVacantQ = mysqli_query($conn, "
                SELECT 
                    (SELECT numrooms FROM room WHERE type='$RoomType') - 
                    (SELECT COUNT(*) FROM roombook WHERE RoomType='$RoomType' AND stat != 'CheckedOut') AS available
            ");
            
            $availableRooms = 0;
            if ($checkVacantQ && mysqli_num_rows($checkVacantQ) > 0) {
                $vacantRow = mysqli_fetch_assoc($checkVacantQ);
                $availableRooms = $vacantRow['available'] ?? 0;
            }
            
            if($availableRooms <= 0) {
                $_SESSION['booking_message'] = "No rooms available - This room type is fully booked. Please choose another.";
                $_SESSION['booking_icon'] = "error";
            } else {
                $sta = "NotConfirm";
                $sql = "INSERT INTO roombook(Name,Email,Country,Phone,RoomType,Bed,NoofRoom,Meal,cin,cout,stat,nodays) 
                        VALUES ('$Name','$Email','N/A','$Phone','$RoomType','N/A','1','N/A','$cin','$cout','$sta',datediff('$cout','$cin'))";
                $result = mysqli_query($conn, $sql);

                if ($result) {
                    $_SESSION['booking_message'] = "Reservation successful - Your booking request has been submitted!";
                    $_SESSION['booking_icon'] = "success";
                } else {
                    $err = mysqli_error($conn);
                    $_SESSION['booking_message'] = "Booking failed - " . $err;
                    $_SESSION['booking_icon'] = "error";
                }
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$approvedReviews = [];
$reviewFetchSql = "SELECT name, room_type, rating, comment, created_at FROM customer_reviews WHERE status = 'approved' ORDER BY created_at DESC LIMIT 6";
$reviewFetchResult = mysqli_query($conn, $reviewFetchSql);
if ($reviewFetchResult) {
    while ($reviewRow = mysqli_fetch_assoc($reviewFetchResult)) {
        $approvedReviews[] = $reviewRow;
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/home.css">
    <title>Dalton Hotel</title>
    <!-- boot -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <!-- fontowesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <!-- sweet alert -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <link rel="stylesheet" href="./admin/css/roombook.css">
    <style>
      #guestdetailpanel{
        display: none;
      }
      #guestdetailpanel .middle{
        height: auto;
        padding: 20px 0;
      }
      .vacant-count {
        display: inline-block;
        background: #28a745;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.9em;
        margin-top: 10px;
        font-weight: bold;
      }
      .vacant-count.low {
        background: #ffc107;
      }
      .vacant-count.none {
        background: #dc3545;
      }
      .bookbtn:disabled {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        cursor: not-allowed;
        opacity: 0.65;
      }
      
      /* Contact Section Styling */
      #contactus {
        padding: 40px 20px;
        background-color: #f8f9fa;
        overflow: hidden;
        position: relative;
      }

      #contactus .social {
        text-align: center;
        margin-bottom: 30px;
      }

      #contactus .social a {
        margin: 0 15px;
        font-size: 2em;
        color: #333;
        transition: color 0.3s;
        text-decoration: none;
      }

      #contactus .social a:hover {
        color: #007bff;
      }

      #contactus iframe {
        display: block;
        max-width: 100%;
        margin: 0 auto;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      }
    </style>
</head>

<body>
  <nav>
    <div class="logo">
      <img class="daltonhotellogo" src=".\image\logo.jpg" alt="logo">
     
    </div>
    <ul>
      <li><a href="#firstsection">Home</a></li>
      <li><a href="#secondsection">Rooms</a></li>
      <li><a href="#thirdsection">Facilites</a></li>
      <li><a href="#reviews">Reviews</a></li>
      <li><a href="#contactus">contact us</a></li>
      <a href="./logout.php"><button class="btn btn-danger">Logout</button></a>
    </ul>
  </nav>

  <section id="firstsection" class="carousel slide carousel_section" data-bs-ride="carousel">
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img class="carousel-image" src="./image/home.jpg">
        </div>
        <div class="carousel-item">
            <img class="carousel-image" src="./image/reception.jpg">
        </div>
        <div class="carousel-item">
            <img class="carousel-image" src="./image/logo.jpg">
        </div>


        <div class="welcomeline">
          <h1 class="welcometag">Welcome to heaven on earth</h1>
        </div>

      <!-- bookbox -->
      <div id="guestdetailpanel">
        <form action="" method="POST" class="guestdetailpanelform">
            <div class="head">
                <h3>RESERVATION</h3>
                <i class="fa-solid fa-circle-xmark" onclick="closebox()"></i>
            </div>
            <div class="middle">
                <div class="guestinfo" style="width: 100%; max-width: 500px; margin: 0 auto;">
                    <h4>Reservation Details</h4>
                    
                    <input type="text" name="Name" id="guestName" placeholder="Enter Full Name" required 
                           onkeypress="return /^[a-zA-Z\s]*$/.test(event.key)" 
                           oninput="this.value = this.value.replace(/[0-9]/g, '')">
                    
                    <input type="email" name="Email" id="guestEmail" placeholder="Enter Email Address" 
                           value="<?php echo htmlspecialchars($loggedInEmail); ?>" required>
                    
                    <input type="text" name="Phone" id="guestPhone" placeholder="Enter Phone Number" required 
                           onkeypress="return /^[0-9+\-\s()]*$/.test(event.key)" 
                           oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                           pattern="[0-9+\-\s()]+" 
                           title="Please enter numbers only">
                    
                    <input type="hidden" name="RoomType" id="selectedRoomType" value="">
                    <input type="text" id="displayRoomType" placeholder="Room Type" readonly style="background-color: #f0f0f0;">
                    
                    <div class="datesection">
                        <span style="width: 48%;">
                            <label for="cin">Check-In</label>
                            <input name="cin" id="checkinDate" type="date" required min="<?php echo date('Y-m-d'); ?>">
                        </span>
                        <span style="width: 48%;">
                            <label for="cout">Check-Out</label>
                            <input name="cout" id="checkoutDate" type="date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </span>
                    </div>
                </div>
            </div>
            <div class="footer">
                <button class="btn btn-success" name="guestdetailsubmit">Submit Reservation</button>
            </div>
        </form>
      </div>

    </div>
  </section>
    
  <section id="secondsection"> 
    <img src="./image/homeanimatebg.svg">
    <div class="ourroom">
      <h1 class="head">≼ Our rooms ≽</h1>
      <div class="roomselect">
        <div class="roombox">
          <div class="hotelphoto h1"></div>
          <div class="roomdata">
            <h2>Superior Room</h2>
            <div class="services">
              <i class="fa-solid fa-wifi"></i>
              <i class="fa-solid fa-burger"></i>
              <i class="fa-solid fa-spa"></i>
              <i class="fa-solid fa-dumbbell"></i>
              <i class="fa-solid fa-person-swimming"></i>
            </div>
            <span class="vacant-count <?php echo $vacantSuperior <= 2 ? ($vacantSuperior == 0 ? 'none' : 'low') : ''; ?>">
              <?php echo $vacantSuperior == 0 ? 'Fully Booked' : $vacantSuperior . ' Room' . ($vacantSuperior > 1 ? 's' : '') . ' Available'; ?>
            </span>
            <button class="btn btn-primary bookbtn" onclick="openbookbox('Superior Room')" <?php echo $vacantSuperior == 0 ? 'disabled' : ''; ?>>
              <?php echo $vacantSuperior == 0 ? 'Fully Booked' : 'Book Now'; ?>
            </button>
          </div>
        </div>
        <div class="roombox">
          <div class="hotelphoto h2"></div>
          <div class="roomdata">
            <h2>Deluxe Room</h2>
            <div class="services">
              <i class="fa-solid fa-wifi"></i>
              <i class="fa-solid fa-burger"></i>
              <i class="fa-solid fa-spa"></i>
              <i class="fa-solid fa-dumbbell"></i>
            </div>
            <span class="vacant-count <?php echo $vacantDeluxe <= 2 ? ($vacantDeluxe == 0 ? 'none' : 'low') : ''; ?>">
              <?php echo $vacantDeluxe == 0 ? 'Fully Booked' : $vacantDeluxe . ' Room' . ($vacantDeluxe > 1 ? 's' : '') . ' Available'; ?>
            </span>
            <button class="btn btn-primary bookbtn" onclick="openbookbox('Deluxe Room')" <?php echo $vacantDeluxe == 0 ? 'disabled' : ''; ?>>
              <?php echo $vacantDeluxe == 0 ? 'Fully Booked' : 'Book Now'; ?>
            </button>
          </div>
        </div>
        <div class="roombox">
          <div class="hotelphoto h3"></div>
          <div class="roomdata">
            <h2>Guest House</h2>
            <div class="services">
              <i class="fa-solid fa-wifi"></i>
              <i class="fa-solid fa-burger"></i>
              <i class="fa-solid fa-spa"></i>
            </div>
            <span class="vacant-count <?php echo $vacantGuestHouse <= 2 ? ($vacantGuestHouse == 0 ? 'none' : 'low') : ''; ?>">
              <?php echo $vacantGuestHouse == 0 ? 'Fully Booked' : $vacantGuestHouse . ' Room' . ($vacantGuestHouse > 1 ? 's' : '') . ' Available'; ?>
            </span>
            <button class="btn btn-primary bookbtn" onclick="openbookbox('Guest House')" <?php echo $vacantGuestHouse == 0 ? 'disabled' : ''; ?>>
              <?php echo $vacantGuestHouse == 0 ? 'Fully Booked' : 'Book Now'; ?>
            </button>
          </div>
        </div>
        <div class="roombox">
          <div class="hotelphoto h4"></div>
          <div class="roomdata">
            <h2>Single Room</h2>
            <div class="services">
              <i class="fa-solid fa-wifi"></i>
              <i class="fa-solid fa-burger"></i>
            </div>
            <span class="vacant-count <?php echo $vacantSingle <= 2 ? ($vacantSingle == 0 ? 'none' : 'low') : ''; ?>">
              <?php echo $vacantSingle == 0 ? 'Fully Booked' : $vacantSingle . ' Room' . ($vacantSingle > 1 ? 's' : '') . ' Available'; ?>
            </span>
            <button class="btn btn-primary bookbtn" onclick="openbookbox('Single Room')" <?php echo $vacantSingle == 0 ? 'disabled' : ''; ?>>
              <?php echo $vacantSingle == 0 ? 'Fully Booked' : 'Book Now'; ?>
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

<section id="thirdsection">
  <h1 class="head">≼ Facilities ≽</h1>
  <div class="facility">
    <div class="box"><h2>Conference Rooms</h2></div>
    <div class="box"><h2>Restaurant</h2></div>
    <div class="box"><h2>Bar</h2></div>
    <div class="box"><h2>Coffee Shop</h2></div>
    
  </div>
</section>

<section id="reviews" class="review-section">
  <div class="review-container">
    <div class="review-form-card">
      <h2>Share Your Stay</h2>
      <p>Tell us how your experience was at Dalton Hotel.</p>
      <form method="POST" class="review-form">
        <label for="review_name">Full Name</label>
        <input type="text" id="review_name" name="review_name" placeholder="Enter your name" required>

        <label for="review_room_type">Room Type</label>
        <select id="review_room_type" name="review_room_type" required>
          <option value="">Select room type</option>
          <option value="Superior Room">Superior Room</option>
          <option value="Deluxe Room">Deluxe Room</option>
          <option value="Guest House">Guest House</option>
          <option value="Single Room">Single Room</option>
        </select>

        <label for="review_rating">Rating</label>
        <select id="review_rating" name="review_rating" required>
          <option value="">Select rating</option>
          <option value="5">5 - ★★★★★</option>
          <option value="4">4 - ★★★★</option>
          <option value="3">3 - ★★★</option>
          <option value="2">2 - ★★</option>
          <option value="1">1 - ★</option>
        </select>

        <label for="review_comment">Comments</label>
        <textarea id="review_comment" name="review_comment" rows="4" placeholder="How was your stay?" required></textarea>

        <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
        <small>Your review may be moderated before it appears.</small>
      </form>
    </div>

    <div class="review-list-card">
      <h2>What Guests Say</h2>
      <div class="review-list">
        <?php if (count($approvedReviews) > 0) : ?>
          <?php foreach ($approvedReviews as $review) : ?>
            <div class="single-review">
              <div class="review-top">
                <h4><?php echo htmlspecialchars($review['name']); ?></h4>
                <div class="review-stars">
                  <?php
                    $rating = (int) $review['rating'];
                    for ($star = 1; $star <= 5; $star++) {
                      $starClass = $star <= $rating ? 'fa-solid' : 'fa-regular';
                      echo "<i class='fa-star $starClass'></i>";
                    }
                  ?>
                </div>
              </div>
              <p class="review-room"><?php echo htmlspecialchars($review['room_type']); ?></p>
              <p class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></p>
              <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
            </div>
          <?php endforeach; ?>
        <?php else : ?>
          <p class="no-review">No reviews yet. Be the first to share your experience!</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

 <section id="contactus">
  <div class="social">
    <a href="https://www.instagram.com/the_dalton_grand_hotel?igsh=dThoeWJjamFtNWxz" target="_blank" rel="noreferrer">
      <i class="fa-brands fa-instagram"></i>
    </a>
    <a href="https://www.facebook.com/TheDaltonGrandHotel" target="_blank" rel="noreferrer">
      <i class="fa-brands fa-facebook"></i>
    </a>
    <a href="https://mail.google.com/mail/?view=cm&to=reservation@thedaltongrandhotel.com">
      <i class="fa-solid fa-envelope"></i>
    </a>
  </div>
  <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.501891369888!2d37.159047099999995!3d-0.7216092!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1828990007e27445%3A0xfa83d2fc92edcf0!2sThe%20Dalton%20Grand%20Hotel!5e0!3m2!1sen!2ske!4v1763653301378!5m2!1sen!2ske" 
      width="100%" 
      height="450" 
      style="border:0; max-width: 1400px;" 
      allowfullscreen="" 
      loading="lazy" 
      referrerpolicy="no-referrer-when-downgrade">
  </iframe>
  </section>
</body>

<script>
    var bookbox = document.getElementById("guestdetailpanel");

    openbookbox = (roomType) => {
      document.getElementById('selectedRoomType').value = roomType;
      document.getElementById('displayRoomType').value = roomType;
      bookbox.style.display = "flex";
    }
    
    closebox = () => {
      bookbox.style.display = "none";
    }
    
    // Update checkout minimum date when checkin changes
    document.getElementById('checkinDate').addEventListener('change', function() {
        var checkinDate = new Date(this.value);
        checkinDate.setDate(checkinDate.getDate() + 1);
        var minCheckout = checkinDate.toISOString().split('T')[0];
        document.getElementById('checkoutDate').min = minCheckout;
    });
</script>

<?php
// Display booking message if exists
if (isset($_SESSION['booking_message'])) {
    $msg = addslashes($_SESSION['booking_message']);
    $icon = $_SESSION['booking_icon'];
    echo "<script>swal({title: 'Booking Status', text: '{$msg}', icon: '{$icon}'});</script>";
    unset($_SESSION['booking_message']);
    unset($_SESSION['booking_icon']);
}

// Display review message if exists
if (isset($_SESSION['review_message'])) {
    $alertText = addslashes($_SESSION['review_message']);
    $alertIcon = $_SESSION['review_icon'];
    echo "<script>swal({title: 'Customer Review', text: '{$alertText}', icon: '{$alertIcon}'});</script>";
    unset($_SESSION['review_message']);
    unset($_SESSION['review_icon']);
}
?>
</html>