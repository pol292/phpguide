<?php
/*** @var $this RecentEvents */

$recentEventsCacheParams = [
    'duration'=>3600,
    'dependency' =>
    [
        'class'=>'system.caching.dependencies.CDbCacheDependency',
        'sql'=>RecentEvents::getCacheDependencySql()
    ]
];

?>

<section id="search_box">
    <form method="get" action="http://www.google.co.il/search" id="search_form">
    
        <input type="hidden" name="hl" value="iw" />
        <input type="checkbox"  name="sitesearch" style="display:none" value="phpguide.co.il" checked />
        
        <input type="text" class="search_form" placeholder="חיפוש" name="q" id="search_field"/>
        <input type="submit" value="" title="לחפש"> 
        
    </form>
</section>
    
    
<?php $this->widget('application.components.LoginBox') ?>

<section class="rblock" style='background-color:rgb(255, 238, 115);'>
	<h3>
		אלכס כותב ספר על תכנות מונחה עצמים למתחילים
	</h3>
	<br/>
	<a href='http://phpguide.co.il/oopbook/' style="font-size:120%; color:dark-blue; font-weight:bold;">
	לחץ כאן כדי לדעת ראשון כשהספר מוכן
	</a>
	
</section>

<?php $this->widget('application.components.RatingWidget'); ?>

<?php if($this->beginCache('RecentEventsFragmentCache', $recentEventsCacheParams)) { ?>

    <?php $this->widget('application.components.RecentEvents'); ?>

<?php $this->endCache(); } ?>

<section class="logos">
	<a href="https://github.com/intval/phpguide" title='phpguide is open source. Help us!' class='github'></a>
	<a href="http://feeds.feedburner.com/phpguideblog" title='All new blog posts via RSS' class='rss'></a>
</section>