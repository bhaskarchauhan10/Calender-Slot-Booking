<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>Book your slot</title>

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

      <div class="toolbar">
                    <span class="toolbar-item">Scale:
                        
                        <label for='scale-hours'><input type="radio" value="hours" name="scale" id='scale-hours'
                                                        checked> Hours</label></span>
        <span class="toolbar-item">Slots: <button id="clear">Clear</button> Deletes all free slots this month</span>

      </div>

      <div id="scheduler"></div>
    </div>

  </div>
</div>

<script src="js/jquery-1.9.1.min.js"></script>
<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  var nav = new DayPilot.Navigator("nav");
  nav.selectMode = "month";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = function (args) {
    //loadEvents(args.start.firstDayOfWeek(), args.start.addDays(7));
    if (scheduler.visibleStart().getDatePart() <= args.day && args.day < scheduler.visibleEnd()) {
      scheduler.scrollTo(args.day, "fast");  // just scroll
    } else {
      loadEvents(args.day);  // reload and scroll
    }
  };
  nav.init();

  var scheduler = new DayPilot.Scheduler("scheduler");
  scheduler.visible = false; // will be displayed after loading the resources
  scheduler.scale = "Manual";
  scheduler.timeline = getTimeline();
  scheduler.timeHeaders = getTimeHeaders();
  scheduler.useEventBoxes = "Never";
  scheduler.eventDeleteHandling = "Update";
  scheduler.eventClickHandling = "Disabled";
  scheduler.eventMoveHandling = "Disabled";
  scheduler.eventResizeHandling = "Disabled";
  scheduler.allowEventOverlap = false,
    scheduler.onBeforeTimeHeaderRender = function (args) {
      args.header.html = args.header.html.replace(" AM", " AM").replace(" PM", " PM");  // shorten the hour header
    };
  scheduler.onBeforeEventRender = function (args) {
    switch (args.data.tags.status) {
      case "free":
        args.data.barColor = "green";
        args.data.deleteDisabled = $('input[name=scale]:checked').val() === "shifts";  // only allow deleting in the more detailed hour scale mode
        break;
      case "waiting":
        args.data.barColor = "orange";
        args.data.deleteDisabled = true;
        break;
      case "confirmed":
        args.data.barColor = "#f41616";  // red
        args.data.deleteDisabled = true;
        break;
    }
  };
  scheduler.onEventDeleted = function (args) {
    var params = {
      id: args.e.id(),
    };
    $.post("backend_delete.php", JSON.stringify(params), function (data) {
      scheduler.message("Deleted.");
    });
  };

  scheduler.onTimeRangeSelected = function (args) {
    var dp = scheduler;
    var scale = $("input[name=scale]:checked").val();

    var params = {
      start: args.start.toString(),
      end: args.end.toString(),
      resource: args.resource,
      scale: scale
    };

    $.post("backend_create.php", JSON.stringify(params), function (data) {
      loadEvents();
      dp.message(data.message);
    });

    dp.clearSelection();

  };
  scheduler.init();


  loadResources();
  loadEvents(DayPilot.Date.today());

  function loadEvents(day) {
    var from = scheduler.visibleStart();
    var to = scheduler.visibleEnd();
    if (day) {
      from = new DayPilot.Date(day).firstDayOfMonth();
      to = from.addMonths(1);
    }

    var params = {
      start: from.toString(),
      end: to.toString()
    };

    $.post("backend_events.php", JSON.stringify(params), function (data) {
      scheduler.timeline = getTimeline(day);
      scheduler.events.list = data;
      scheduler.update();
      scheduler.scrollTo(day, "fast", "left");

      nav.events.list = data;
      nav.update();
    });
  }

  function loadResources() {
    $.post("backend_resources.php", function (data) {
      scheduler.resources = data;
      scheduler.visible = true;
      scheduler.update();
    });
  }

  function getTimeline(date) {
    var date = date || DayPilot.Date.today();
    var start = new DayPilot.Date(date).firstDayOfMonth();
    var days = start.daysInMonth();
    var scale = $("input[name=scale]:checked").val();
    var businessOnly = $("#business-only").prop("checked");

    var morningShiftStarts = 9;
    var morningShiftEnds = 13;
    var afternoonShiftStarts = 14;
    var afternoonShiftEnds = 18;

    if (!businessOnly) {
      var morningShiftStarts = 0;
      var morningShiftEnds = 12;
      var afternoonShiftStarts = 12;
      var afternoonShiftEnds = 24;
    }

    var timeline = [];

    var increaseMorning;  // in hours
    var increaseAfternoon;  // in hours
    switch (scale) {
      case "hours":
        increaseMorning = 1;
        increaseAfternoon = 1;
        break;
      default:
        throw "Invalid scale value";
    }

    for (var i = 0; i < days; i++) {
      var day = start.addDays(i);

      for (var x = morningShiftStarts; x < morningShiftEnds; x += increaseMorning) {
        timeline.push({start: day.addHours(x), end: day.addHours(x + increaseMorning)});
      }
      for (var x = afternoonShiftStarts; x < afternoonShiftEnds; x += increaseAfternoon) {
        timeline.push({start: day.addHours(x), end: day.addHours(x + increaseAfternoon)});
      }
    }

    return timeline;
  }

  function getTimeHeaders() {
    var scale = $('input[name=scale]:checked').val();
    switch (scale) {
      case "15min":
        return [{groupBy: "Month"}, {groupBy: "Day", format: "dddd d"}, {
          groupBy: "Hour",
          format: "h tt"
        }, {groupBy: "Cell", format: "m"}];
        break;
      case "hours":
        return [{groupBy: "Month"}, {groupBy: "Day", format: "dddd d"}, {groupBy: "Hour", format: "h tt"}];
        break;
      case "shifts":
        return [{groupBy: "Month"}, {groupBy: "Day", format: "dddd d"}, {groupBy: "Cell", format: "tt"}];
        break;
    }
  }

  $(document).ready(function () {
    $("#business-only").click(function () {
      scheduler.timeline = getTimeline();
      scheduler.update();
    });
    $("input[name=scale]").click(function () {
      scheduler.timeline = getTimeline();
      scheduler.timeHeaders = getTimeHeaders();
      scheduler.update();
    });
    $("#clear").click(function () {
      var dp = scheduler;
      var params = {
        start: dp.visibleStart(),
        end: dp.visibleEnd()
      };
      $.post("backend_clear.php", JSON.stringify(params), function (data) {
        dp.message(data.message);
        loadEvents();
      });
    });
  });

</script>

</body>
</html>
