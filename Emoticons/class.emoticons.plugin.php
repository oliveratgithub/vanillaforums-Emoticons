<?php if (!defined('APPLICATION')) exit();

/**
 * Define the plugin:
 */
$PluginInfo['Emoticons'] = array(
	'Name' 		=>	 'Emoticons',
	'Description' => 'Replaces text emoticons like :-) and :-P with graphics.',
	'Version' 	=>	 '1.4',
	'Author' 	=>	 'Oliver Raduner',
	'AuthorEmail' => 'vanilla@raduner.ch',
	'AuthorUrl' =>	 'http://raduner.ch/',
	'License' =>	 'Free',
	'RequiredPlugins' => FALSE,
	'HasLocale' => FALSE,
	'RegisterPermissions' => FALSE,
	'SettingsUrl' => FALSE,
	'SettingsPermission' => FALSE,
	'MobileFriendly' => TRUE,
	'RequiredApplications' => array('Vanilla' => '>=2.0.16')
);


/**
 * Emoticons Plugin
 *
 * Replaces text emoticons like <code>:-)</code> and <code>:-P</code> with graphics.
 *
 * @version 1.4
 * @date 09-JAN-2011
 * @author Oliver Raduner <vanilla@raduner.ch>
 *
 * @todo Add Emoticons also in other areas such as Activities, Profilestatus and Messages
 * @todo Performance improvements necessary??
 */
class EmoticonsPlugin extends Gdn_Plugin
{
	
	/**
	 * Add the Plugin Stylesheet to the Header
	 *
	 * @version 1.2
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses EMOTICONS_PLUGIN_ROOT
	 */
	public function DiscussionController_Render_Before($Sender)
	{
		$Sender->AddCssFile($this->GetResource('emoticon.css', FALSE, FALSE));
	}
	
	
	/**
	 * Initialize everything we need to replace Emoticons with Graphics
	 *
	 * @version 1.0
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 * 
	 * @todo The Emoticon<-->Filename-Match could probably be exculed to a JSON-formatted file?
	 *
	 * @global array $EmoticonMatch
	 * @global array $EmoticonsSearch
	 */
	public function Initialize()
	{
		global $EmoticonMatch, $EmoticonsSearch;
		
		// Build an Array containing the Emoticon<-->Graphic matches
		if (!isset($EmoticonMatch))
		{
			$EmoticonMatch = array(
				'8-)'	=> 'cool.gif',
				':-E'	=> 'evil.gif',
				'8-|'	=> 'goofy.gif',
				'<3'	=> 'heart.gif',
				'8-?'	=> 'huh.gif',
				':-D'	=> 'laugh.gif',
				':D'	=> 'laugh.gif',
				'>:-('	=> 'mad.gif',
				'(8)'	=> 'ninja.gif',
				'8-x'	=> 'ohno.gif',
				'8-P'	=> 'puke.gif',
				':?:'	=> 'question.gif',
				':-P'	=> 'razz.gif',
				':P'	=> 'razz.gif',
				':-('	=> 'sad.gif',
				':('	=> 'sad.gif',
				':-O'	=> 'scream.gif',
				':-)'	=> 'smile.gif',
				'=)'	=> 'smile.gif',
				'^^'	=> 'smile.gif',
				':)'	=> 'smile.gif',
				';-|'	=> 'suspect.gif',
				':!:'	=> 'warning.gif',
				';-)'	=> 'wink.gif',
				';)'	=> 'wink.gif',
			); // Add more matches, if you need them... Put the corresponding graphics into the Plugin's images-folder
		}
		
		// In case there's something wrong with the Array, exit the Function
		if (count($EmoticonMatch) == 0)
			return;
		
		// Define the basic Regex pattern to find Emoticons
		$EmoticonsSearch = '/(?:\s|^)';
		
		// Automatically extend the Regex pattern based on the Emoticon-Codes in the $EmoticonMatch-Array
		$subchar = '';
		foreach ( (array) $EmoticonMatch as $Smiley => $Img ) {
			$firstchar = substr($Smiley, 0, 1);
			$rest = substr($Smiley, 1);
	
			// new subpattern?
			if ($firstchar != $subchar) {
				if ($subchar != '') {
					$EmoticonsSearch .= ')|(?:\s|^)';
				}
				$subchar = $firstchar;
				$EmoticonsSearch .= preg_quote($firstchar, '/') . '(?:';
			} else {
				$EmoticonsSearch .= '|';
			}
			$EmoticonsSearch .= preg_quote($rest, '/');
		}
		
		// Add final Regex pattern to the Search-Variable
		$EmoticonsSearch .= ')(?:\s|$)/m';
		
	}
	
	
	/**
	 * Hack the Discussion-Controller to replace Text with Emoticons in Discussions & Comments
	 * 
	 * @version 1.3
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses Initialize()
	 * @uses FindEmoticon()
	 */
	//public function DiscussionController_BeforeCommentBody_Handler($Sender)
	public function DiscussionController_BeforeCommentBody_Handler($Sender)
	{
		// Get the current Discussion and Comments
		$Comment = $Sender->Discussion;
		
		// Initialize the Emoticons stuff
		$this->Initialize();
		
		// Replace Emoticons in the Discussion and all Comments to it
		$Comment->Body = $this->FindEmoticon($Comment->Body);
		
		foreach($Sender->CommentData as $cdata) {
			$cdata->Body = $this->FindEmoticon($cdata->Body);;
		}
	}
	
	
	/**
	 * Hack the Post-Controller to replace Text with Emoticons in just submitted Comments
	 * 
	 * @version 1.3
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses Initialize()
	 * @uses FindEmoticon()
	 */
	public function PostController_BeforeCommentBody_Handler($Sender)
	{
		// Initialize the Emoticons stuff
		$this->Initialize();
		
		// Replace Emoticons in a Discussion & Comment just submitted
		$this->DiscussionController_BeforeCommentBody_Handler($Sender);
	}
	
	
	/**
	 * Hack the Post-Controller to replace Text with Emoticons in the Discussion Preview
	 * 
	 * @version 1.3
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses Initialize()
	 * @uses FindEmoticon()
	 */
	public function PostController_BeforeDiscussionRender_Handler($Sender)
	{
		if ($Sender->View == 'preview')
		{
			// Initialize the Emoticons stuff
			$this->Initialize();
			
			// Replace Emoticons in a preview of a new Discussion
			$Sender->Comment->Body = $this->FindEmoticon($Sender->Comment->Body);
		}
	}
	
	
	/**
	 * Hack the Post-Controller to replace Text with Emoticons in Comment Previews
	 * 
	 * @version 1.3
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses Initialize()
	 * @uses FindEmoticon()
	 */
	public function PostController_BeforeCommentRender_Handler($Sender)
	{
		if ($Sender->View == 'preview')
		{
			// Initialize the Emoticons stuff
			$this->Initialize();
			
			// Replace Emoticons in a preview to a new Comment
			$Sender->Comment->Body = $this->FindEmoticon($Sender->Comment->Body);
		}
	}
	
	
	/**
	 * Hack the Post-Controller to replace Text with Emoticons in Edit-Previews
	 *
 	 * @version 1.3
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses Initialize()
	 * @uses FindEmoticon()
	 */
	public function PostController_BeforeDiscussionPreview_Handler($Sender)
	{
		// Initialize the Emoticons stuff
		$this->Initialize();
		
		// Replace Emoticons in a preview of a currently edited Discussion or Comment
		$Sender->Comment->Body = $this->FindEmoticon($Sender->Comment->Body);
	}
	
	
	/**
	 * Search through a Text and find any occurence of an Emoticon
	 *
	 * @version 1.0
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 *
	 * @uses $EmoticonImgTag()
	 * @global array $EmoticonsSearch()
	 * @param string $Text Content to convert Emoticons from.
	 * @return string Converted string with text emoticons replaced by <img>-tag.
	 */
	public function FindEmoticon($Text)
	{
		global $EmoticonsSearch;
		
		$Output = '';
		$Content = '';
		
		// Check if the Emoticons-Searchstring has been set properly
		if (!empty($EmoticonsSearch) )
		{
			$TextArr = preg_split("/(<.*>)/U", $Text, -1, PREG_SPLIT_DELIM_CAPTURE); // Capture the Tags as well as in between
			$Stop = count($TextArr);
			
			for ($i = 0; $i < $Stop; $i++)
			{
				$Content = $TextArr[$i];
				
				// Check if it's not a HTML-Tag
				if ((strlen($Content) > 0) && ('<' != $Content{0}))
				{
					// Documentation about preg_replace_callback: http://php.net/manual/en/function.preg-replace-callback.php
					$Content = preg_replace_callback($EmoticonsSearch, array(&$this, 'EmoticonImgTag'), $Content);
				}
				
				$Output .= $Content;
			}
			
		} else {
			// Return default text
			$Output = $Text;
		}
		
		return $Output;
	}
	
	
	/**
	 * Translate an Emoticon Code into <img> HTML-tag
	 * 
	 * @version 1.1
	 * @since 1.0
	 * @author Oliver Raduner <vanilla@raduner.ch>
	 * 
	 * @uses EMOTICONS_PLUGIN_ROOT
	 * @global array $EmoticonMatch
	 * @param string $Emoticon The Emoticon Code to convert to image.
	 * @return string HTML-Image-Tag string for the emoticon.
	 */
	public function EmoticonImgTag($Emoticon)
	{
		global $EmoticonMatch;
		
		if (count($Emoticon) == 0) {
			return '';
		}
		
		$Emoticon = trim(reset($Emoticon));
		$Img = $EmoticonMatch[$Emoticon];
		$EmoticonMasked = $Emoticon;
		
		return ' <img class="emoticon" src="'.C('Garden.WebRoot').$this->GetResource("images/$Img", FALSE, FALSE).'" alt="'.$EmoticonMasked.'" /> ';
	}
	
	
	/**
	 * Initialize required data
	 *
	 * @version 1.1
	 * @since 1.0
	 */
	public function Setup() { }
	
}

?>