/**
 * wrap - Word wrap text to a fixed column width
 *
 * @author  Ryan Uber <ryan@blankbmx.com>
 * @link    http://www.ryanuber.com/word-wrapping-in-ansi-c.html
 *
 * This function will wrap large amounts of a text into a manageable and human-readable width
 * word by word. Just specify the number of columns you are working with and feed it a string,
 * and it will return a new string (including added line breaks) to accommodate the area you
 * are working with.
 *
 * {{{ proto( void ) wrap( char out, char str, int columns )
 */
void wrap( char *out, char *str, int columns )
{
    int len, n, w, wordlen=0, linepos=0, outlen=0;
 
    /*
     * Find length of string 'str' without using string.h
     */
    for( len=0; str[len]; ++len );
 
    /*
     * Allocate the full space of 'str' to 'word', so there is no possible way that the string
     * could contain a word that does not fit into the 'word' variable.
     */
    char word[len];
 
    /*
     * Loop through each individual character in the passed array (str) and detect white space
     * and word length to determine how to handle line wrapping.
     */
    for( n=0; n<=len; n++ )
    {
        /*
         * Detect spaces and newlines. We cannot gurantee that the passed string is null-
         * terminated, so we also need to handle cases where we reach the end of the string
         * without encountering wite space characters.
         */
        if( str[n] == ' ' || str[n] == '\n' || n == len )
        {
            /*
             * If the current word will not fit on the current line, add a newline.
             */
            if( linepos > columns )
            {
                out[outlen++] = '\n';
                linepos = wordlen;
            }
 
            /*
             * Append the found word to the output and reset the word character array in
             * preparation for accepting characters for the next word.
             */
            for( w=0; w<wordlen; w++ )
            {
                out[outlen++] = word[w];
                word[w] = '\0';
            }
 
            /*
             * If we reach the end of the string, add the null-terminator character.
             */
            if( n == len )
                out[outlen] = '\0';
 
            /*
             * If we encounter a newline character, append it to the string as usual, but
             * set the line position counter back to 0 (new line = start count from 0 again).
             */
            else if( str[n] == '\n' )
            {
                out[outlen] = str[n];
                linepos=0;
            }
 
            /*
             * If the word fits in the current line without trouble, just add the space.
             */
            else
            {
                out[outlen] = ' ';
                linepos++;
            }
 
            /*
             * Increment the final output length for the next loop, and set the word length
             * counter back to 0 (newline or space = new word).
             */
            outlen++;
            wordlen=0;
        }
 
        /*
         * If the current character is in the middle of a word somewhere, just append it and
         * move on, incrementing counters.
         */
        else
        {
            word[wordlen++] = str[n];
            linepos++;
        }
    }
}
/* }}} */
