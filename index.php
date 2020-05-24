<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>Calender Time Booking System</title>

  <link type="text/css" rel="stylesheet" href="css/layout.css"/>

  <!-- DayPilot library -->
  <script src="js/daypilot/daypilot-all.min.js"></script>
</head>
<body>
<?php require_once '_header.php'; ?>

<div class="main">
  <?php require_once '_navigation.php'; ?>

  <div>

    <div class="column-left">
      <div id="nav"></div>
    </div>
    <div class="column-main">
      <div class="toolbar">Available time slots:</div>
      <div id="calendar"></div>
    </div>

  </div>
</div>

<script src="js/jquery-1.9.1.min.js"></script>
<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  var nav = new DayPilot.Navigator("nav");
  nav.selectMode = "week";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = function (args) {
    loadEvents(args.start.firstDayOfWeek(DayPilot.Locale.find(nav.locale).weekStarts), args.start.addDays(7));
  };
  nav.init();

  var calendar = new DayPilot.Calendar("calendar");
  calendar.viewType = "Week";
  calendar.timeRangeSelectedHandling = "Disabled";
  calendar.eventMoveHandling = "Disabled";
  calendar.eventResizeHandling = "Disabled";
  calendar.onBeforeEventRender = function (args) {
    if (!args.data.tags) {
      return;
    }
    switch (args.data.tags.status) {
      case "free":
        args.data.barColor = "green";
        args.data.html = "Available<br/>" + args.data.tags.doctor;
        args.data.toolTip = "Click to request this time slot";
        break;
      case "waiting":
        args.data.barColor = "orange";
        args.data.html = "Your appointment, waiting for confirmation";
        break;
      case "confirmed":
        args.data.barColor = "#f41616";  // red
        args.data.html = "Your appointment, confirmed";
        break;
    }
  };
  calendar.onEventClick = function (args) {
    if (args.e.tag("status") !== "free") {
      calendar.message("You can only request a new appointment in a free slot.");
      return;
    }

    var modal = new DayPilot.Modal({
      onClosed: function (args) {
        if (args.result) {  // args.result is empty when modal is closed without submitting
          loadEvents();
        }
      }
    });

    modal.showUrl("appointment_request.php?id=" + args.e.id());
  };
  calendar.init();

  loadEvents();

  function loadEvents(day) {
    var start = nav.visibleStart() > new DayPilot.Date() ? nav.visibleStart() : new DayPilot.Date();

    var params = {
      start: start.toString(),
      end: nav.visibleEnd().toString()
    };

    $.post("backend_events_free.php", JSON.stringify(params), function (data) {
      if (day) {
        calendar.startDate = day;
      }
      calendar.events.list = data;
      calendar.update();

      nav.events.list = data;
      nav.update();

    });
  }
</script>

</body>
</html>
